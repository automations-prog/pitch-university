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

const CLOSING_LINE =
    "Thanks so much for your time — welcome to Pitch University, and we'll follow up shortly on next steps.";

/**
 * The `response.audio_transcript.done`-style event this timer is keyed off
 * fires once the closing line's *text* is finalized, not once the TTS audio
 * has finished playing it aloud. A flat 4s delay was cutting the goodbye
 * line off mid-sentence — 18 words takes ~6-7s to actually speak — so this
 * is sized off the real script line instead of a guessed constant, with a
 * buffer for playback/network jitter.
 */
const SPEECH_WORDS_PER_MINUTE = 150;
const HANGUP_BUFFER_MS = 2_500;

function estimateSpeechDurationMs(text: string): number {
    const wordCount = text.trim().split(/\s+/).filter(Boolean).length;

    return Math.round((wordCount / SPEECH_WORDS_PER_MINUTE) * 60_000);
}

const AUTO_HANGUP_DELAY_MS =
    estimateSpeechDurationMs(CLOSING_LINE) + HANGUP_BUFFER_MS;

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

export function useRealtimeCall({ token }: { token: string }) {
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
                transcriptRef.current = [
                    ...transcriptRef.current,
                    { role: 'assistant', text },
                ];

                const lower = text.toLowerCase();

                if (lower.includes(PITCH_PROMPT_MARKER)) {
                    pitchArmedRef.current = true;
                }

                if (
                    lower.includes(CLOSING_MARKER) &&
                    !hangupTimeoutRef.current
                ) {
                    hangupTimeoutRef.current = setTimeout(() => {
                        void endCall();
                    }, AUTO_HANGUP_DELAY_MS);
                }

                return;
            }

            if (type && CANDIDATE_TRANSCRIPT_EVENTS.has(type) && text) {
                transcriptRef.current = [
                    ...transcriptRef.current,
                    { role: 'candidate', text },
                ];
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
                await fetch(completeCall.url(token), {
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
        [token],
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

            const sessionResponse = await fetch(createCallSession.url(token), {
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
                remoteAudio.srcObject = trackEvent.streams[0];
                audioContext
                    .createMediaStreamSource(trackEvent.streams[0])
                    .connect(destination);
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
                                turn_detection: {
                                    type: 'semantic_vad',
                                    eagerness: 'low',
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
    }, [cleanup, handleServerEvent, sendEvent, token]);

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
