/**
 * Speaks the roleplay consumer's lines through ElevenLabs while OpenAI
 * Realtime decides what they say (in text). One WebSocket for the whole
 * call (the token works once), with one ElevenLabs context per consumer
 * line, keyed by OpenAI's item id, so a line can be cut off on its own.
 *
 * A line that arrives while another is playing waits its turn, as OpenAI's
 * own audio did; only `cancel()` cuts a line off.
 *
 * Audio arrives as raw 24kHz PCM and is scheduled into `output`, which the
 * call hook routes to the consumer's <audio> element, level meter and
 * recording, exactly where OpenAI's own audio went.
 */

export type TtsConfig = { token: string; voice_id: string; model_id: string };

export type ElevenLabsSpeaker = {
    /** Resolves `true` once the WebSocket is open, `false` if it failed. */
    ready: Promise<boolean>;
    /** Adds streamed text to a line. */
    append: (lineId: string, text: string) => void;
    /** No more text is coming for this line. */
    finish: (lineId: string) => void;
    /** Stops the line being spoken now and drops it, and any waiting. */
    cancel: () => void;
    /** A line is still being generated or played. */
    isSpeaking: () => boolean;
    close: () => void;
};

const SAMPLE_RATE = 24_000;
/** The connection stays open this long without text (ElevenLabs' max). */
const INACTIVITY_TIMEOUT_S = 180;
/** Resets that timer on long silences, such as the transfer ringing. */
const KEEP_ALIVE_MS = 60_000;
/** A little lead so the first chunk of a line doesn't start clipped. */
const SCHEDULE_LEAD_S = 0.05;
/** A finished line whose `isFinal` never arrives counts as fully sent after this. */
const FINISH_STALL_MS = 5_000;

const VOICE_SETTINGS = {
    stability: 0.4,
    similarity_boost: 0.8,
    style: 0.2,
    use_speaker_boost: true,
};

type Line = {
    id: string;
    hasStarted: boolean;
    isFinished: boolean;
    /** ElevenLabs has sent all of the line's audio. */
    isFinal: boolean;
    /** Audio held back until the line before it has finished playing. */
    heldAudio: string[];
    sources: Set<AudioBufferSourceNode>;
    stallTimeout: ReturnType<typeof setTimeout> | null;
};

export function createElevenLabsSpeaker({
    config,
    audioContext,
    output,
    onStarted,
    onStopped,
    onCleared,
    onError,
}: {
    config: TtsConfig;
    audioContext: AudioContext;
    output: AudioNode;
    /** The line's first audio started playing. */
    onStarted: (lineId: string) => void;
    /** The line finished playing. */
    onStopped: (lineId: string) => void;
    /** The line was cut off by `cancel()` after it started playing. */
    onCleared: (lineId: string) => void;
    onError: (message: string) => void;
}): ElevenLabsSpeaker {
    const params = new URLSearchParams({
        model_id: config.model_id,
        output_format: `pcm_${SAMPLE_RATE}`,
        inactivity_timeout: String(INACTIVITY_TIMEOUT_S),
        single_use_token: config.token,
    });
    const socket = new WebSocket(
        `wss://api.elevenlabs.io/v1/text-to-speech/${encodeURIComponent(config.voice_id)}/multi-stream-input?${params}`,
    );

    /** The line playing first, then the lines waiting their turn. */
    let lines: Line[] = [];
    /** Lines whose late audio is ignored. */
    const droppedLineIds = new Set<string>();
    let nextStartAt = 0;
    let isClosing = false;
    let lastSentAt = Date.now();

    const send = (message: Record<string, unknown>) => {
        if (socket.readyState === WebSocket.OPEN) {
            socket.send(JSON.stringify(message));
            lastSentAt = Date.now();
        }
    };

    const keepAlive = setInterval(() => {
        const last = lines.at(-1);

        if (last && Date.now() - lastSentAt >= KEEP_ALIVE_MS) {
            send({ text: '', context_id: last.id });
        }
    }, KEEP_ALIVE_MS / 2);

    const ready = new Promise<boolean>((resolve) => {
        socket.addEventListener('open', () => resolve(true));
        socket.addEventListener('error', () => resolve(false));
        socket.addEventListener('close', (closeEvent) => {
            resolve(false);

            if (!isClosing) {
                onError(
                    `The consumer's voice disconnected (${closeEvent.code}${closeEvent.reason ? `: ${closeEvent.reason}` : ''}).`,
                );
            }
        });
    });

    const clearStall = (line: Line) => {
        if (line.stallTimeout) {
            clearTimeout(line.stallTimeout);
            line.stallTimeout = null;
        }
    };

    const play = (line: Line, base64: string) => {
        const bytes = Uint8Array.from(atob(base64), (char) =>
            char.charCodeAt(0),
        );
        const samples = new Int16Array(
            bytes.buffer,
            0,
            Math.floor(bytes.length / 2),
        );

        if (samples.length === 0) {
            return;
        }

        const buffer = audioContext.createBuffer(
            1,
            samples.length,
            SAMPLE_RATE,
        );
        const channel = buffer.getChannelData(0);

        for (let i = 0; i < samples.length; i++) {
            channel[i] = samples[i] / 32_768;
        }

        const source = audioContext.createBufferSource();
        source.buffer = buffer;
        source.connect(output);

        const startAt = Math.max(
            nextStartAt,
            audioContext.currentTime + SCHEDULE_LEAD_S,
        );
        source.start(startAt);
        nextStartAt = startAt + buffer.duration;
        line.sources.add(source);

        source.onended = () => {
            line.sources.delete(source);
            source.disconnect();
            settleIfDone(line);
        };

        if (!line.hasStarted) {
            line.hasStarted = true;
            onStarted(line.id);
        }
    };

    /**
     * Ends the playing line once it's finished, all its audio is in and
     * played, then starts the next one with the audio it was holding.
     */
    const settleIfDone = (line: Line) => {
        if (
            lines[0] !== line ||
            line.sources.size > 0 ||
            !line.isFinished ||
            !line.isFinal
        ) {
            return;
        }

        clearStall(line);
        lines.shift();

        if (line.hasStarted) {
            onStopped(line.id);
        }

        const next = lines[0];

        if (next) {
            for (const audio of next.heldAudio.splice(0)) {
                play(next, audio);
            }
            settleIfDone(next);
        }
    };

    socket.addEventListener('message', (messageEvent) => {
        let message: {
            audio?: string;
            isFinal?: boolean;
            contextId?: string;
            error?: string;
            message?: string;
        };

        try {
            message = JSON.parse(messageEvent.data as string);
        } catch {
            return;
        }

        if (message.error) {
            // eslint-disable-next-line no-console
            console.error('[elevenlabs] error', message);

            return;
        }

        const line = lines.find(({ id }) => id === message.contextId);

        if (!line || droppedLineIds.has(line.id)) {
            return;
        }

        if (message.audio) {
            if (lines[0] === line) {
                play(line, message.audio);
            } else {
                line.heldAudio.push(message.audio);
            }
        }

        if (message.isFinal) {
            line.isFinal = true;
            settleIfDone(line);
        }
    });

    /** Drops every line; a cut-off is only reported when asked to. */
    const dropAll = (reportsCutOff: boolean) => {
        for (const line of lines) {
            clearStall(line);
            droppedLineIds.add(line.id);
            send({ context_id: line.id, close_context: true });

            for (const source of line.sources) {
                source.onended = null;

                try {
                    source.stop();
                } catch {
                    // Never started.
                }
                source.disconnect();
            }

            if (reportsCutOff && line.hasStarted) {
                onCleared(line.id);
            }
        }

        lines = [];
        nextStartAt = 0;
    };

    return {
        ready,
        append: (lineId, text) => {
            if (droppedLineIds.has(lineId) || !text) {
                return;
            }

            let line = lines.find(({ id }) => id === lineId);

            if (!line) {
                line = {
                    id: lineId,
                    hasStarted: false,
                    isFinished: false,
                    isFinal: false,
                    heldAudio: [],
                    sources: new Set(),
                    stallTimeout: null,
                };
                lines.push(line);
                send({
                    text: ' ',
                    context_id: lineId,
                    voice_settings: VOICE_SETTINGS,
                });
            }

            send({ text, context_id: lineId });
        },
        finish: (lineId) => {
            const line = lines.find(({ id }) => id === lineId);

            if (!line || line.isFinished) {
                return;
            }

            line.isFinished = true;
            // Closing after the flush makes ElevenLabs send `isFinal` once
            // the line's last audio is out.
            send({ text: '', context_id: lineId, flush: true });
            send({ context_id: lineId, close_context: true });
            line.stallTimeout = setTimeout(() => {
                line.stallTimeout = null;
                line.isFinal = true;
                settleIfDone(line);
            }, FINISH_STALL_MS);
        },
        cancel: () => dropAll(true),
        isSpeaking: () => lines.length > 0,
        close: () => {
            isClosing = true;
            clearInterval(keepAlive);
            dropAll(false);
            send({ close_socket: true });
            socket.close();
        },
    };
}
