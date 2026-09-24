import { useCallback, useEffect, useRef, useState } from 'react';
import { xsrfHeader } from '@/lib/csrf';
import {
    complete as completeCall,
    session as createCallSession,
} from '@/routes/screening/call';

/**
 * Drives the candidate-facing AI voice call over OpenAI's Realtime API via
 * WebRTC, straight from the browser.
 *
 * IMPORTANT — this talks to a third-party realtime API whose exact event
 * names/wire format can change between OpenAI API versions. Verify the
 * `session.update` / `response.create` / server event shapes below against
 * the current OpenAI Realtime API docs before relying on this in
 * production; this was written without access to a live session to test
 * against.
 *
 * The script itself (`task1.md`) is fixed and linear: greet + ask about
 * the candidate, ask for a 15-second pitch, interrupt with "why should I
 * keep talking to you" ~10s into that pitch, then close. Only the
 * interrupt is enforced by the app (a timer keyed off when the candidate
 * starts speaking) rather than left to the model, per the spec's
 * requirement that it be a fixed beat, not content-driven.
 */

const REALTIME_CALLS_URL = 'https://api.openai.com/v1/realtime/calls';

const SCRIPT_INSTRUCTIONS = `You are conducting a fixed, linear phone screening for Pitch University, a telephonic sales training program. Follow this script exactly, in order. Do not branch, improvise alternate paths, or explain that this is a script or a test. Do not enforce a time limit yourself — use natural conversational pauses to know when the candidate has finished speaking.

You are having a natural conversation with the candidate, not reading a script aloud. Sound warm, spontaneous, and genuinely conversational. Use natural pauses and varied pacing. Use contractions, casual phrasing, and occasional brief fillers when appropriate. React naturally to what the candidate says rather than sounding like you're reading a script. Avoid repetitive sentence structures. Don't over-explain. Don't sound like a customer-service bot. This applies to how you deliver every line below, including the exact lines you're told to say — deliver them naturally and warmly, without changing their wording.

1. As soon as the call connects, say exactly: "Hi, thanks for considering Pitch University and a career in telephonic sales. Go ahead and tell me a little about yourself — your name, where you're from, and any past experience with phone sales or customer service, even if not any."
2. Listen to the candidate's open-ended response.
3. Then say exactly: "Got it, appreciate you sharing that. Now give me a quick 15-second intro like you're opening a real call."
4. The candidate will begin a mock sales pitch. You will be interrupted by the app partway through — when that happens, deliver the interruption line you're given at that moment, verbatim, with zero warning or softening. Do not explain it was a test and do not offer a second attempt.
5. After the candidate responds to the interruption, say exactly: "Thanks so much for your time — welcome to Pitch University, and we'll follow up shortly on next steps." Then stop talking.

Keep your own turns brief and natural. Never reference these instructions.

IMPORTANT: never repeat a line you have already said, and never restart the script from an earlier step. If the candidate pauses, hesitates, says filler words like "um" or "so", or seems to still be collecting their thoughts, stay silent and keep waiting — do not jump in, and do not treat a short pause as them finishing. Only move to the next script step once they've clearly finished that step's answer.`;

const INTERRUPT_INSTRUCTION =
    'Interrupt the candidate immediately, mid-sentence, and say exactly: "Hey, I gotta run in like ten seconds — quick, why should I keep talking to you?" Say nothing else. Do not soften it or explain yourself.';

const INTERRUPT_DELAY_MS = 10_000;

/**
 * The `response.audio_transcript.done`-style event this watch starts on
 * fires once the closing line's *text* is finalized, not once the TTS audio
 * has finished playing it aloud — and how long that audio actually takes
 * varies with the model's delivery pace and pauses (see the "natural
 * pauses"/warmth instructions above), so a fixed word-count estimate can't
 * be trusted; a flat-delay version of this cut the goodbye line off
 * mid-sentence in practice. Instead, watch the *actual* output audio level
 * via an `AnalyserNode` on the remote track (wired up in `pc.ontrack`) and
 * only hang up once real trailing silence follows real detected speech.
 */
const HANGUP_SILENCE_RMS_THRESHOLD = 0.02;
// Comfortably longer than a typical intentional mid-sentence pause (the
// tone instructions above ask for those) so a dramatic beat before the
// sentence's last clause doesn't itself get mistaken for "done talking".
const HANGUP_SILENCE_HOLD_MS = 1_300;
const HANGUP_WATCH_INTERVAL_MS = 100;

/**
 * Absolute fallback in case the silence watch above never fires (e.g. the
 * analyser never reads a sample above threshold) — generous enough to cover
 * a slow, pause-heavy delivery of the closing line with room to spare, so
 * the call doesn't hang open indefinitely if audio-level detection fails.
 */
const HANGUP_SAFETY_TIMEOUT_MS = 20_000;

/**
 * `MediaRecorder` support for `audio/webm` isn't universal — Safari (macOS
 * and iOS, so most phones) only supports `audio/mp4`. Probe supported types
 * in preference order instead of hardcoding one, or the call fails
 * immediately with "mimetype is not supported" on any browser that doesn't
 * support the first pick. Extensions match `StoreScreeningCallRequest`'s
 * allowed `mimes:webm,wav,ogg,mp3,m4a` list.
 */
const RECORDING_MIME_CANDIDATES: { mimeType: string; extension: string }[] = [
    { mimeType: 'audio/webm;codecs=opus', extension: 'webm' },
    { mimeType: 'audio/webm', extension: 'webm' },
    { mimeType: 'audio/ogg;codecs=opus', extension: 'ogg' },
    { mimeType: 'audio/ogg', extension: 'ogg' },
    { mimeType: 'audio/mp4', extension: 'm4a' },
];

function pickSupportedRecordingType(): {
    mimeType: string;
    extension: string;
} {
    const supported = RECORDING_MIME_CANDIDATES.find(({ mimeType }) =>
        MediaRecorder.isTypeSupported(mimeType),
    );

    if (!supported) {
        throw new Error(
            'This browser does not support any compatible audio recording format.',
        );
    }

    return supported;
}

/**
 * Recognize the AI's own scripted lines by their (known, instructed)
 * wording rather than by counting `response.done` events. Counting turns
 * turned out to be unreliable in practice — it's unclear whether the
 * Realtime API always emits exactly one `response.done` per logical script
 * beat, and a live test ended the call after only two exchanges, right
 * where a miscounted turn total would prematurely hit an assumed "closing"
 * turn number. Matching the actual text we told the model to say is far
 * more robust since we fully control that wording.
 */
const PITCH_PROMPT_MARKER = '15-second intro';
const CLOSING_MARKER = 'follow up shortly';

// NOTE: the Realtime API's exact transcript event names have moved between
// API versions (e.g. `response.audio_transcript.done` vs
// `response.output_audio_transcript.done`; `conversation.item.input_audio_transcription.completed`
// vs `conversation.item.audio_transcription.completed`). Rather than
// betting on one exact name, these match a set of known candidates plus a
// generic fallback on any event carrying a `transcript` string — verify
// the real names against a live session's dev-console logging.
const ASSISTANT_TRANSCRIPT_EVENTS = new Set([
    'response.audio_transcript.done',
    'response.output_audio_transcript.done',
    'response.content_part.done',
]);
const CANDIDATE_TRANSCRIPT_EVENTS = new Set([
    'conversation.item.input_audio_transcription.completed',
    'conversation.item.audio_transcription.completed',
]);

export type CallPhase = 'idle' | 'connecting' | 'active' | 'ended' | 'error';

export type TranscriptEntry = {
    role: 'assistant' | 'candidate';
    text: string;
};

type EphemeralSession = {
    value?: string;
    session?: { id?: string };
};

export function useRealtimeCall({ token: responseToken }: { token: string }) {
    const [phase, setPhase] = useState<CallPhase>('idle');
    const [muted, setMuted] = useState(false);
    const [elapsedSeconds, setElapsedSeconds] = useState(0);
    const [error, setError] = useState<string | null>(null);

    const peerConnectionRef = useRef<RTCPeerConnection | null>(null);
    const dataChannelRef = useRef<RTCDataChannel | null>(null);
    const localStreamRef = useRef<MediaStream | null>(null);
    const audioContextRef = useRef<AudioContext | null>(null);
    const recorderRef = useRef<MediaRecorder | null>(null);
    const recordedChunksRef = useRef<Blob[]>([]);
    const remoteAudioElRef = useRef<HTMLAudioElement | null>(null);

    const transcriptRef = useRef<TranscriptEntry[]>([]);
    const pitchArmedRef = useRef(false);
    const interruptTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(
        null,
    );
    const hangupTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const hangupWatchIntervalRef = useRef<ReturnType<
        typeof setInterval
    > | null>(null);
    const remoteAnalyserRef = useRef<AnalyserNode | null>(null);
    const elapsedIntervalRef = useRef<ReturnType<typeof setInterval> | null>(
        null,
    );
    const endingRef = useRef(false);
    const initialResponseSentRef = useRef(false);
    const callInProgressRef = useRef(false);
    const recordingTypeRef = useRef<{ mimeType: string; extension: string }>({
        mimeType: 'audio/webm',
        extension: 'webm',
    });
    const wiredRemoteStreamIdRef = useRef<string | null>(null);

    const sendEvent = useCallback((event: Record<string, unknown>) => {
        if (dataChannelRef.current?.readyState === 'open') {
            dataChannelRef.current.send(JSON.stringify(event));
        }
    }, []);

    const armInterruptTimer = useCallback(() => {
        if (interruptTimeoutRef.current) {
            return;
        }

        interruptTimeoutRef.current = setTimeout(() => {
            // Cancel whatever the model thinks it's doing first, so the
            // interrupt line reliably barges in rather than queuing behind
            // an in-progress turn.
            sendEvent({ type: 'response.cancel' });
            sendEvent({
                type: 'response.create',
                response: { instructions: INTERRUPT_INSTRUCTION },
            });
        }, INTERRUPT_DELAY_MS);
    }, [sendEvent]);

    const handleServerEvent = useCallback(
        (event: Record<string, unknown>) => {
            const type = event.type as string | undefined;

            if (import.meta.env.DEV) {
                // eslint-disable-next-line no-console
                console.debug('[realtime-call]', type, event);
            }

            if (type === 'error') {
                // Always logged (not just DEV) — if the script isn't being
                // followed, this is the first place to look: a rejected
                // `session.update`/`response.create` shows up here.
                // eslint-disable-next-line no-console
                console.error('[realtime-call] server error', event);
                return;
            }

            if (
                (type === 'session.updated' || type === 'session.created') &&
                !initialResponseSentRef.current
            ) {
                // Fallback in case the primary attempt (sent immediately
                // after `session.update`, see the data channel `open`
                // handler) somehow didn't land — guarded by the same ref so
                // this never double-fires.
                initialResponseSentRef.current = true;
                sendEvent({ type: 'response.create' });
                return;
            }

            if (type === 'input_audio_buffer.speech_started') {
                if (pitchArmedRef.current) {
                    armInterruptTimer();
                }
                return;
            }

            const text = (event.transcript as string | undefined) ?? '';

            if (type && ASSISTANT_TRANSCRIPT_EVENTS.has(type) && text) {
                transcriptRef.current.push({ role: 'assistant', text });

                const lower = text.toLowerCase();

                if (lower.includes(PITCH_PROMPT_MARKER)) {
                    pitchArmedRef.current = true;
                }

                if (
                    lower.includes(CLOSING_MARKER) &&
                    !hangupTimeoutRef.current &&
                    !hangupWatchIntervalRef.current
                ) {
                    beginClosingLineHangupWatch();
                }

                return;
            }

            if (type && CANDIDATE_TRANSCRIPT_EVENTS.has(type) && text) {
                transcriptRef.current.push({ role: 'candidate', text });
            }
        },
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [armInterruptTimer, sendEvent],
    );

    const cleanup = useCallback(() => {
        if (interruptTimeoutRef.current) {
            clearTimeout(interruptTimeoutRef.current);
            interruptTimeoutRef.current = null;
        }
        if (hangupTimeoutRef.current) {
            clearTimeout(hangupTimeoutRef.current);
            hangupTimeoutRef.current = null;
        }
        if (hangupWatchIntervalRef.current) {
            clearInterval(hangupWatchIntervalRef.current);
            hangupWatchIntervalRef.current = null;
        }
        if (elapsedIntervalRef.current) {
            clearInterval(elapsedIntervalRef.current);
            elapsedIntervalRef.current = null;
        }

        dataChannelRef.current?.close();
        dataChannelRef.current = null;

        peerConnectionRef.current?.close();
        peerConnectionRef.current = null;

        localStreamRef.current?.getTracks().forEach((track) => track.stop());
        localStreamRef.current = null;

        if (
            audioContextRef.current &&
            audioContextRef.current.state !== 'closed'
        ) {
            void audioContextRef.current.close();
        }
        audioContextRef.current = null;

        remoteAudioElRef.current?.remove();
        remoteAudioElRef.current = null;

        initialResponseSentRef.current = false;
        pitchArmedRef.current = false;
        callInProgressRef.current = false;
        wiredRemoteStreamIdRef.current = null;
        remoteAnalyserRef.current = null;
    }, []);

    const uploadRecording = useCallback(
        async (blob: Blob) => {
            const transcript = transcriptRef.current
                .map((entry) => `${entry.role}: ${entry.text}`)
                .join('\n');

            const formData = new FormData();
            formData.append('transcript', transcript);
            formData.append(
                'recording',
                blob,
                `call.${recordingTypeRef.current.extension}`,
            );

            try {
                await fetch(completeCall.url(responseToken), {
                    method: 'POST',
                    body: formData,
                    headers: xsrfHeader(),
                    credentials: 'same-origin',
                });
            } catch {
                // Best-effort: the call already happened for the candidate;
                // a failed upload shouldn't block them from finishing.
            }
        },
        [responseToken],
    );

    const endCall = useCallback(async () => {
        if (endingRef.current) {
            return;
        }
        endingRef.current = true;

        const recorder = recorderRef.current;

        const finish = async () => {
            const blob = new Blob(recordedChunksRef.current, {
                type: recordingTypeRef.current.mimeType,
            });
            cleanup();
            if (blob.size > 0) {
                await uploadRecording(blob);
            }
            setPhase('ended');
        };

        if (recorder && recorder.state !== 'inactive') {
            recorder.onstop = () => {
                void finish();
            };
            recorder.stop();
        } else {
            await finish();
        }
    }, [cleanup, uploadRecording]);

    /**
     * Waits for the closing line's audio to actually finish playing (via
     * real-time RMS level on the remote track's `AnalyserNode`) before
     * hanging up, rather than guessing a fixed delay from the script text —
     * see the constants above for why. Requires having heard the AI
     * actually speak first, so a brief gap between the text finalizing and
     * audio starting doesn't read as "already silent, done talking".
     */
    const beginClosingLineHangupWatch = useCallback(() => {
        const analyser = remoteAnalyserRef.current;

        if (!analyser) {
            hangupTimeoutRef.current = setTimeout(() => {
                void endCall();
            }, HANGUP_SAFETY_TIMEOUT_MS);
            return;
        }

        const levels = new Uint8Array(analyser.fftSize);
        let hasHeardSpeech = false;
        let silenceStartedAt: number | null = null;

        hangupWatchIntervalRef.current = setInterval(() => {
            analyser.getByteTimeDomainData(levels);

            let sumSquares = 0;
            for (let i = 0; i < levels.length; i++) {
                const normalized = (levels[i] - 128) / 128;
                sumSquares += normalized * normalized;
            }
            const rms = Math.sqrt(sumSquares / levels.length);

            if (rms > HANGUP_SILENCE_RMS_THRESHOLD) {
                hasHeardSpeech = true;
                silenceStartedAt = null;
                return;
            }

            if (!hasHeardSpeech) {
                return;
            }

            silenceStartedAt ??= Date.now();

            if (Date.now() - silenceStartedAt >= HANGUP_SILENCE_HOLD_MS) {
                if (hangupWatchIntervalRef.current) {
                    clearInterval(hangupWatchIntervalRef.current);
                    hangupWatchIntervalRef.current = null;
                }
                void endCall();
            }
        }, HANGUP_WATCH_INTERVAL_MS);

        hangupTimeoutRef.current = setTimeout(() => {
            if (hangupWatchIntervalRef.current) {
                clearInterval(hangupWatchIntervalRef.current);
                hangupWatchIntervalRef.current = null;
            }
            void endCall();
        }, HANGUP_SAFETY_TIMEOUT_MS);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const toggleMute = useCallback(() => {
        const stream = localStreamRef.current;
        if (!stream) {
            return;
        }

        setMuted((current) => {
            const next = !current;
            stream.getAudioTracks().forEach((track) => {
                track.enabled = !next;
            });
            return next;
        });
    }, []);

    const start = useCallback(async () => {
        if (callInProgressRef.current) {
            return;
        }
        callInProgressRef.current = true;

        setPhase('connecting');
        setError(null);
        endingRef.current = false;
        transcriptRef.current = [];

        try {
            // Ask for the mic *before* minting the ephemeral session — the
            // permission prompt can take an unpredictable amount of time,
            // and the ephemeral key is short-lived.
            const localStream = await navigator.mediaDevices.getUserMedia({
                audio: true,
            });
            localStreamRef.current = localStream;

            const sessionResponse = await fetch(createCallSession.url(responseToken), {
                method: 'POST',
                headers: xsrfHeader(),
                credentials: 'same-origin',
            });

            if (!sessionResponse.ok) {
                const body = await sessionResponse.text().catch(() => '');
                throw new Error(
                    `Could not start the call session (HTTP ${sessionResponse.status}). ${body}`,
                );
            }

            const session = (await sessionResponse.json()) as EphemeralSession;
            const ephemeralKey = session.value;

            if (!ephemeralKey) {
                throw new Error('Missing realtime session credentials.');
            }

            const pc = new RTCPeerConnection();
            peerConnectionRef.current = pc;

            localStream
                .getTracks()
                .forEach((track) => pc.addTrack(track, localStream));

            const audioContext = new AudioContext();
            audioContextRef.current = audioContext;
            const destination = audioContext.createMediaStreamDestination();
            audioContext
                .createMediaStreamSource(localStream)
                .connect(destination);

            const remoteAudio = document.createElement('audio');
            remoteAudio.autoplay = true;
            remoteAudioElRef.current = remoteAudio;

            pc.ontrack = (trackEvent) => {
                const remoteStream = trackEvent.streams[0];
                remoteAudio.srcObject = remoteStream;

                if (wiredRemoteStreamIdRef.current === remoteStream.id) {
                    return;
                }
                wiredRemoteStreamIdRef.current = remoteStream.id;

                const remoteSource =
                    audioContext.createMediaStreamSource(remoteStream);
                remoteSource.connect(destination);

                const analyser = audioContext.createAnalyser();
                analyser.fftSize = 512;
                remoteSource.connect(analyser);
                remoteAnalyserRef.current = analyser;
            };

            recordingTypeRef.current = pickSupportedRecordingType();
            const recorder = new MediaRecorder(destination.stream, {
                mimeType: recordingTypeRef.current.mimeType,
            });
            recordedChunksRef.current = [];
            recorder.ondataavailable = (dataEvent) => {
                if (dataEvent.data.size > 0) {
                    recordedChunksRef.current.push(dataEvent.data);
                }
            };
            recorderRef.current = recorder;

            const dataChannel = pc.createDataChannel('oai-events');
            dataChannelRef.current = dataChannel;

            dataChannel.addEventListener('open', () => {
                sendEvent({
                    type: 'session.update',
                    session: {
                        type: 'realtime',
                        instructions: SCRIPT_INSTRUCTIONS,
                        audio: {
                            input: {
                                // `semantic_vad` waits out hesitations like
                                // "um"/"so" instead of treating any brief
                                // silence as the candidate finishing — a
                                // live test with `server_vad` cut the
                                // candidate off mid-thought repeatedly.
                                // `eagerness: 'low'` (the most patient
                                // setting) fixed that but left a noticeable
                                // 5-10s dead-air gap before the AI's next
                                // line; `medium` keeps enough patience to
                                // avoid the cutoff while responding faster.
                                turn_detection: {
                                    type: 'semantic_vad',
                                    eagerness: 'medium',
                                },
                                transcription: { model: 'gpt-transcribe' },
                            },
                        },
                    },
                });

                // Ordered, reliable data channel — the server processes
                // messages in the order sent, so this is applied after the
                // update above without needing to wait for confirmation.
                // (`handleServerEvent` has a same-ref-guarded fallback in
                // case that assumption ever turns out to be wrong.)
                initialResponseSentRef.current = true;
                sendEvent({ type: 'response.create' });

                recorder.start(1000);
                setPhase('active');
                elapsedIntervalRef.current = setInterval(() => {
                    setElapsedSeconds((seconds) => seconds + 1);
                }, 1000);
            });

            dataChannel.addEventListener('message', (messageEvent) => {
                try {
                    handleServerEvent(JSON.parse(messageEvent.data as string));
                } catch {
                    // Ignore malformed/unrecognized events.
                }
            });

            const offer = await pc.createOffer();
            await pc.setLocalDescription(offer);

            const sdpResponse = await fetch(REALTIME_CALLS_URL, {
                method: 'POST',
                body: offer.sdp,
                headers: {
                    Authorization: `Bearer ${ephemeralKey}`,
                    'Content-Type': 'application/sdp',
                },
            });

            if (!sdpResponse.ok) {
                const body = await sdpResponse.text().catch(() => '');
                throw new Error(
                    `Could not connect to the realtime call (HTTP ${sdpResponse.status}). ${body}`,
                );
            }

            const answerSdp = await sdpResponse.text();
            await pc.setRemoteDescription({ type: 'answer', sdp: answerSdp });
        } catch (caught) {
            cleanup();
            setError(
                caught instanceof Error
                    ? caught.message
                    : 'Could not start the call.',
            );
            setPhase('error');
        }
    }, [cleanup, handleServerEvent, sendEvent, responseToken]);

    useEffect(() => cleanup, [cleanup]);

    return {
        phase,
        muted,
        elapsedSeconds,
        error,
        start,
        toggleMute,
        endCall,
    };
}
