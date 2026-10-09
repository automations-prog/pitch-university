import { useCallback, useEffect, useRef, useState } from 'react';
import { xsrfHeader } from '@/lib/csrf';
import {
    playRingback,
    remoteAudioPlayback,
    RINGBACK_CYCLE_MS,
} from '@/lib/ringback';
import type { RoleplaySessionResult } from '@/lib/roleplay-data';
import {
    complete as completeCall,
    session as createCallSession,
} from '@/routes/roleplay/call';

/**
 * Drives a live roleplay call: the trainee reads the script, and an AI
 * consumer answers over OpenAI's Realtime API via WebRTC.
 *
 * WebRTC plumbing, recording and the hang-up silence watch are copied from
 * `use-realtime-call.ts` (the screening call); see the notes there. What's
 * different:
 *
 * - The prompt and tools are minted into the session server-side
 *   (`RoleplayConsumerPrompt`), so this sends no `session.update`. The
 *   persona's outcome and DQ trap never ship with the page.
 * - OpenAI's turn detection is off: the page finds the trainee's turns
 *   from the mic level, commits them and asks for each reply itself.
 * - The model reports patience and objections through function calls,
 *   answered with `function_call_output` so it keeps going.
 * - Nothing is uploaded when the call ends. The trainee codes the call
 *   first, then `complete()` uploads everything and returns the grade.
 *
 * IMPORTANT — as with the screening call, verify the event names and the
 * function-calling shapes below against the current OpenAI Realtime docs
 * before relying on this in production.
 */

const REALTIME_CALLS_URL = 'https://api.openai.com/v1/realtime/calls';

const HANGUP_SILENCE_RMS_THRESHOLD = 0.02;
const HANGUP_SILENCE_HOLD_MS = 1_300;
const HANGUP_WATCH_INTERVAL_MS = 100;
const HANGUP_SAFETY_TIMEOUT_MS = 20_000;

/**
 * Delivery scoring (AI_CALL_PLAN §9) needs when each side was talking.
 * The consumer's speech is read off the remote audio level (independent of
 * event names); the agent's from the page's own turn detection (see
 * `AGENT_TURN_RMS_THRESHOLD`).
 */
const LEVEL_WATCH_INTERVAL_MS = 100;
const SPEECH_RMS_THRESHOLD = 0.02;
const CONSUMER_SILENCE_HOLD_MS = 600;
const LOUDNESS_SAMPLE_MS = 1_000;
const MAX_EVENTS = 5_000;

/**
 * Ringing between clicking Transfer and the specialist joining. The
 * trainee fluffs with the consumer the whole time, as on the floor.
 */
const TRANSFER_FLUFF_MS = 30_000;
const TRANSFER_RING_VOLUME = 0.04;

/** The specialist's pre-recorded "Who am I speaking with?". */
const SPECIALIST_AUDIO_URL = '/audio/roleplay/specialist-intro.mp3';
const SPECIALIST_AUDIO_MAX_MS = 6_000;

/** Trainee speech this long while the specialist is on counts as talking over them. */
const TALK_OVER_HOLD_MS = 800;

/**
 * The trainee's mic is held until a greeting has been heard, or this long
 * at most (long enough for the retries below).
 */
const GREETING_MIC_HOLD_MAX_MS = 15_000;

/**
 * The "Hello?" transcript arrives whether or not its audio played, so the
 * greeting is checked against the consumer's audio level and asked for
 * again when nothing was heard: the first try plus two retries.
 */
const GREETING_MAX_ATTEMPTS = 3;
const GREETING_WATCH_INTERVAL_MS = 50;
/** After the greeting's audio stops, for the last of it to reach the analyser. */
const GREETING_STOPPED_TAIL_MS = 300;
/** After the greeting's response is done, in case its audio never starts or stops. */
const GREETING_DONE_GRACE_MS = 3_000;
/**
 * The greeting counts as heard once the consumer's audio was above the
 * speech level for this long in total; a single 50ms blip used to pass.
 */
const GREETING_MIN_HEARD_MS = 200;
/**
 * The ringback plays on its own AudioContext, closed when it ends, and a
 * headset or speaker can take a moment to wake back up for the consumer's
 * audio. "Hello?" is short enough to be lost entirely in that gap, so the
 * pick-up waits this long after the ringing ends.
 */
const GREETING_WARMUP_MS = 700;

/**
 * After End call, how long the connection stays open for the trainee's
 * last words to be transcribed. Transcription lands a beat after each turn
 * is committed, so tearing down at once dropped the final line.
 */
const FINAL_TRANSCRIPT_WAIT_MS = 2_000;
const FINAL_TRANSCRIPT_POLL_MS = 100;

/** The error OpenAI sends when the final commit finds nothing new to transcribe. */
const EMPTY_COMMIT_ERROR_CODE = 'input_audio_buffer_commit_empty';

const AGENT_TRANSCRIPT_FAILED_EVENT =
    'conversation.item.input_audio_transcription.failed';

const SPECIALIST_LINE = 'Hi, who am I speaking with?';

/**
 * OpenAI's turn detection is off (`turn_detection: null`): on WebRTC it
 * cleared the consumer's audio the moment it took any sound on the
 * trainee's mic for speech, even with `interrupt_response` off. The page
 * finds the trainee's turns from the mic level instead, so the consumer is
 * never cut off and noise never reaches the model.
 *
 * A turn starts once the mic is above the threshold for `START_MS` (a
 * quiet stretch of `START_QUIET_RESET_MS` resets that), and ends after
 * `END_SILENCE_MS` of quiet. It's committed and answered only if the mic
 * was loud for `MIN_LOUD_MS` in total; anything less was noise and is
 * cleared. While the trainee is quiet, the buffer is cleared every
 * `IDLE_CLEAR_MS` so room noise doesn't pile up into their next turn.
 */
const AGENT_TURN_RMS_THRESHOLD = 0.02;
const AGENT_TURN_START_MS = 200;
const AGENT_TURN_START_QUIET_RESET_MS = 300;
const AGENT_TURN_END_SILENCE_MS = 800;
/** Levels 4–5 answer sooner, so the consumer feels pushier. */
const PUSHY_AGENT_TURN_END_SILENCE_MS = 500;
const PUSHY_MIN_LEVEL = 4;
const AGENT_TURN_MIN_LOUD_MS = 300;
const AGENT_TURN_IDLE_CLEAR_MS = 2_000;
const AGENT_TURN_WATCH_INTERVAL_MS = 50;

/**
 * The trainee talking over the consumer, who keeps talking, as on a real
 * call. It counts once the mic is this loud for this long in total; a
 * quiet stretch this long resets it, so coughs, typing and chatter nearby
 * don't add up.
 */
const BARGE_IN_RMS_THRESHOLD = 0.05;
const BARGE_IN_HOLD_MS = 600;
const BARGE_IN_QUIET_RESET_MS = 400;

/**
 * Echo cancellation never removes all of the consumer's voice from the
 * mic, least of all on laptop speakers, and what's left read as the
 * trainee talking: the consumer then answered its own echo ("Morning." →
 * "Warning."). While the consumer is audible, and for `ECHO_TAIL_MS`
 * after, the mic has to beat the echo expected from the consumer's level
 * by `ECHO_MARGIN` to count as the trainee.
 *
 * How much of the consumer leaks into the mic (the echo ratio) is learned
 * from the call itself, starting at `ECHO_RATIO_INITIAL`, and the gate
 * never rises above `ECHO_GATE_MAX_RMS` so a real barge-in still counts.
 * While the consumer is talking and the trainee isn't, the input buffer is
 * cleared every `ECHO_CLEAR_MS` so the echo never reaches the model, and
 * the mic is turned down to `RECORDING_ECHO_DUCK_GAIN` in the recording so
 * the consumer isn't heard twice.
 */
const ECHO_TAIL_MS = 400;
const ECHO_MARGIN = 2.5;
const ECHO_RATIO_INITIAL = 0.15;
const ECHO_RATIO_MAX = 0.5;
const ECHO_RATIO_SMOOTHING = 0.05;
const ECHO_GATE_MAX_RMS = 0.1;
const ECHO_CLEAR_MS = 500;
const RECORDING_ECHO_DUCK_GAIN = 0.15;
const RECORDING_GAIN_TIME_CONSTANT_S = 0.03;

/**
 * A turn that started while the consumer was audible is answered only once
 * its transcript is in, and dropped if it mostly repeats what the consumer
 * just said: that's the consumer's echo, not the trainee. Words match when
 * they're within an edit distance of a third of their length.
 */
const ECHO_TRANSCRIPT_MATCH_RATIO = 0.6;
const ECHO_TRANSCRIPT_MAX_WORDS = 8;
const ECHO_TRANSCRIPT_RECENT_LINES = 2;

/** Tags the replies we ask for, so an error can be traced back to one. */
const REPLY_EVENT_ID_PREFIX = 'reply_';
const ACTIVE_RESPONSE_ERROR_CODE = 'conversation_already_has_active_response';

const TRANSFER_STARTED_MESSAGE =
    "(The caller is transferring you to a Medicare specialist and you can hear it ringing. The specialist hasn't picked up yet. While you wait, chat casually with the caller: answer their small talk in a sentence or two, like the weather or your day. Don't raise new objections and don't hang up.)";

const SPECIALIST_JOINS_MESSAGE = `(A licensed Medicare specialist just picked up and says: "${SPECIALIST_LINE}" Answer the specialist.)`;

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

// Event names have moved between Realtime API versions; match the known
// candidates, same as the screening call.
const ASSISTANT_TRANSCRIPT_EVENTS = new Set([
    'response.audio_transcript.done',
    'response.output_audio_transcript.done',
]);
const AGENT_TRANSCRIPT_EVENTS = new Set([
    'conversation.item.input_audio_transcription.completed',
    'conversation.item.audio_transcription.completed',
]);

export type RoleplayCallPhase =
    | 'idle'
    | 'connecting'
    | 'ringing'
    | 'active'
    | 'transferring'
    | 'ended'
    | 'error';

export type RoleplayEndReason = 'agent' | 'hung_up';

/** Where a cold transfer is: ringing (fluff), specialist talking, lead answered. */
export type TransferStage = 'ringing' | 'specialist' | 'answered';

export type LiveTranscriptLine = {
    id: number;
    speaker: 'agent' | 'consumer' | 'system';
    text: string;
    /** Seconds into the call the line was spoken. */
    at: number;
};

type EphemeralSession = { value?: string };

type FunctionCall = { call_id?: string; name?: string; arguments?: string };

/** Matches `RoleplayDeliveryMetrics::EVENT_FIELDS`. Times are ms into the call. */
type CallEvent =
    | { type: 'agent_speech' | 'consumer_speech'; start: number; end: number }
    | {
          type: 'objection_raised' | 'objection_resolved';
          id: string;
          at: number;
      }
    | { type: 'patience_changed'; patience: number; reason: string; at: number }
    | {
          type:
              | 'transfer_clicked'
              | 'specialist_joined'
              | 'lead_answered_specialist'
              | 'transfer_completed'
              | 'agent_talked_on_connect'
              | 'consumer_interrupted';
          at: number;
      }
    | { type: 'agent_loudness'; rms: number; at: number }
    | { type: 'echo_discarded'; at: number }
    | {
          type: 'audio_checked';
          at: number;
          echo_cancellation: number;
          noise_suppression: number;
          auto_gain_control: number;
      }
    | {
          type: 'greeting_checked';
          at: number;
          attempt: number;
          heard: number;
          heard_ms: number;
          peak_rms: number;
      };

function rmsOf(
    analyser: AnalyserNode,
    levels: Uint8Array<ArrayBuffer>,
): number {
    analyser.getByteTimeDomainData(levels);

    let sumSquares = 0;
    for (let i = 0; i < levels.length; i++) {
        const normalized = (levels[i] - 128) / 128;
        sumSquares += normalized * normalized;
    }

    return Math.sqrt(sumSquares / levels.length);
}

function wordsOf(text: string): string[] {
    return text
        .toLowerCase()
        .replace(/[^a-z0-9' ]/g, ' ')
        .split(/\s+/)
        .filter(Boolean);
}

function editDistance(a: string, b: string): number {
    let previous = Array.from({ length: b.length + 1 }, (_, i) => i);

    for (let i = 1; i <= a.length; i++) {
        const current = [i];

        for (let j = 1; j <= b.length; j++) {
            current[j] = Math.min(
                previous[j] + 1,
                current[j - 1] + 1,
                previous[j - 1] + (a[i - 1] === b[j - 1] ? 0 : 1),
            );
        }
        previous = current;
    }

    return previous[b.length];
}

/**
 * Whether a short trainee turn is mostly the consumer's recent words heard
 * back through the mic. Transcription garbles echo, so words match loosely.
 */
function isEchoOfConsumer(text: string, consumerLines: string[]): boolean {
    const words = wordsOf(text);
    const consumerWords = consumerLines.flatMap(wordsOf);

    if (
        words.length === 0 ||
        words.length > ECHO_TRANSCRIPT_MAX_WORDS ||
        consumerWords.length === 0
    ) {
        return false;
    }

    const matched = words.filter((word) =>
        consumerWords.some(
            (consumerWord) =>
                editDistance(word, consumerWord) <=
                Math.max(1, Math.floor(word.length / 3)),
        ),
    ).length;

    return matched / words.length >= ECHO_TRANSCRIPT_MATCH_RATIO;
}

function formatOffset(totalSeconds: number): string {
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;

    return `${minutes}:${seconds.toString().padStart(2, '0')}`;
}

function parseArguments(raw: string | undefined): Record<string, unknown> {
    try {
        return JSON.parse(raw ?? '{}') as Record<string, unknown>;
    } catch {
        return {};
    }
}

export function useRoleplayRealtimeCall({
    sessionId,
    level,
    startingPatience,
}: {
    sessionId: number;
    level: number;
    startingPatience: number;
}) {
    const [phase, setPhase] = useState<RoleplayCallPhase>('idle');
    const [muted, setMuted] = useState(false);
    const [elapsedSeconds, setElapsedSeconds] = useState(0);
    const [patience, setPatience] = useState(startingPatience);
    const [openObjections, setOpenObjections] = useState<string[]>([]);
    const [transcript, setTranscript] = useState<LiveTranscriptLine[]>([]);
    const [endReason, setEndReason] = useState<RoleplayEndReason | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [audioBlocked, setAudioBlocked] = useState(false);
    const [transferStage, setTransferStage] = useState<TransferStage | null>(
        null,
    );

    const peerConnectionRef = useRef<RTCPeerConnection | null>(null);
    const dataChannelRef = useRef<RTCDataChannel | null>(null);
    const localStreamRef = useRef<MediaStream | null>(null);
    const audioContextRef = useRef<AudioContext | null>(null);
    const recorderRef = useRef<MediaRecorder | null>(null);
    const recordedChunksRef = useRef<Blob[]>([]);
    const recordingRef = useRef<Blob | null>(null);
    const remoteAudioElRef = useRef<HTMLAudioElement | null>(null);
    const remoteAnalyserRef = useRef<AnalyserNode | null>(null);
    const wiredRemoteStreamIdRef = useRef<string | null>(null);
    const recordingDestinationRef =
        useRef<MediaStreamAudioDestinationNode | null>(null);
    const micSenderRef = useRef<RTCRtpSender | null>(null);
    const specialistAudioRef = useRef<HTMLAudioElement | null>(null);
    const recordingTypeRef = useRef({
        mimeType: 'audio/webm',
        extension: 'webm',
    });

    const callStartedAtRef = useRef<number | null>(null);
    const elapsedIntervalRef = useRef<ReturnType<typeof setInterval> | null>(
        null,
    );
    const hangupTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const hangupWatchIntervalRef = useRef<ReturnType<
        typeof setInterval
    > | null>(null);
    const transferTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(
        null,
    );
    const stopRingbackRef = useRef<(() => void) | null>(null);
    const greetingMicTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(
        null,
    );
    /**
     * Until the consumer's greeting has played, the mic is detached from the
     * call (the track itself stays enabled; muting is separate).
     */
    const heldMicRef = useRef<{
        sender: RTCRtpSender;
        track: MediaStreamTrack;
    } | null>(null);

    const transcriptRef = useRef<LiveTranscriptLine[]>([]);
    /** The transcript line each consumer item became, to mark it cut off. */
    const consumerLineIdsRef = useRef(new Map<string, number>());
    /** Consumer items whose audio was cut before it finished playing. */
    const cutOffItemsRef = useRef(new Set<string>());
    /** The consumer item whose audio is playing now. */
    const playingItemIdRef = useRef<string | null>(null);
    /** Samples the trainee's mic all call to find their turns. */
    const agentTurnWatchIntervalRef = useRef<ReturnType<
        typeof setInterval
    > | null>(null);
    /** The trainee's turn in progress, if they're talking. */
    const agentTurnRef = useRef<{
        loudMs: number;
        lastLoudAt: number;
        /** It started while the consumer was audible, so it may be echo. */
        mayBeEcho: boolean;
    } | null>(null);
    /** The turn being committed, read when the commit lands. */
    const nextCommitRef = useRef<{
        mayBeEcho: boolean;
        speechEvent: CallEvent | null;
    } | null>(null);
    /**
     * Committed turns held back from a reply until their transcript is
     * checked for echo, with their speech event to drop if it is.
     */
    const echoCheckItemsRef = useRef(new Map<string, CallEvent | null>());
    /** The trainee's mic into the recording, turned down while the consumer talks. */
    const micRecordingGainRef = useRef<GainNode | null>(null);
    /** The mic is off the call for good (the specialist joined). */
    const micOffCallRef = useRef(false);
    /** A turn ended while the consumer was still answering; reply after. */
    const replyPendingRef = useRef(false);
    /** Set when we cancel the consumer ourselves, so it isn't an interruption. */
    const cancelledByUsRef = useRef(false);
    /** A consumer response is being generated, so there's one to cancel. */
    const responseActiveRef = useRef(false);
    /** When each conversation item started, so lines sort by when they were spoken. */
    const itemStartedAtRef = useRef(new Map<string, number>());
    const transferClickedAtRef = useRef<number | null>(null);
    const awaitingSpecialistReplyRef = useRef(false);
    /** Between the specialist joining and the lead answering them. */
    const specialistOnLineRef = useRef(false);
    /** The greeting waits for the consumer's audio to be unblocked. */
    const pendingGreetingRef = useRef(false);
    /** The greeting being played, until it's been heard (or given up on). */
    const greetingRef = useRef<{
        attempt: number;
        itemId: string | null;
        /** How long the consumer's audio was above the speech level. */
        heardMs: number;
        /** Whether the consumer's audio level could be read at all. */
        couldMeasure: boolean;
        peakRms: number;
        /** Its transcript, held back until the greeting has been heard. */
        transcript: { text: string; at: number } | null;
    } | null>(null);
    const greetingWarmupTimeoutRef = useRef<ReturnType<
        typeof setTimeout
    > | null>(null);
    const greetingWatchIntervalRef = useRef<ReturnType<
        typeof setInterval
    > | null>(null);
    const greetingCheckTimeoutRef = useRef<ReturnType<
        typeof setTimeout
    > | null>(null);
    /** Greetings that were never heard, whose transcript lines are dropped. */
    const discardedItemsRef = useRef(new Set<string>());
    /**
     * Whether the consumer has spoken since the trainee's last turn, so a
     * tool-only response doesn't make them answer twice.
     */
    const spokeSinceAgentTurnRef = useRef(false);
    const pendingEndReasonRef = useRef<RoleplayEndReason | null>(null);
    const callInProgressRef = useRef(false);
    /** Trainee turns committed but not yet transcribed, by item id. */
    const pendingTranscriptsRef = useRef(new Set<string>());
    /** The final commit sent on End call, until OpenAI answers it. */
    const awaitingFinalCommitRef = useRef(false);
    const endingRef = useRef(false);

    const eventsRef = useRef<CallEvent[]>([]);
    const micAnalyserRef = useRef<AnalyserNode | null>(null);
    const levelWatchIntervalRef = useRef<ReturnType<typeof setInterval> | null>(
        null,
    );
    const agentSpeechStartRef = useRef<number | null>(null);
    const consumerSpeechStartRef = useRef<number | null>(null);
    const consumerQuietSinceRef = useRef<number | null>(null);

    const secondsIntoCall = useCallback(
        () =>
            callStartedAtRef.current === null
                ? 0
                : Math.floor((Date.now() - callStartedAtRef.current) / 1000),
        [],
    );

    const msIntoCall = useCallback(
        () =>
            callStartedAtRef.current === null
                ? 0
                : Date.now() - callStartedAtRef.current,
        [],
    );

    const logEvent = useCallback((event: CallEvent) => {
        if (eventsRef.current.length < MAX_EVENTS) {
            eventsRef.current.push(event);
        }
    }, []);

    /** Close any speech segment still open when the call ends. */
    const flushSpeech = useCallback(() => {
        const end = msIntoCall();

        if (agentSpeechStartRef.current !== null) {
            logEvent({
                type: 'agent_speech',
                start: agentSpeechStartRef.current,
                end,
            });
            agentSpeechStartRef.current = null;
        }

        if (consumerSpeechStartRef.current !== null) {
            logEvent({
                type: 'consumer_speech',
                start: consumerSpeechStartRef.current,
                end: consumerQuietSinceRef.current ?? end,
            });
            consumerSpeechStartRef.current = null;
        }
    }, [logEvent, msIntoCall]);

    /**
     * Samples both audio levels: the consumer's to find when they speak,
     * and the trainee's mic while they speak, for the monotone check.
     */
    const startLevelWatch = useCallback(() => {
        const remoteLevels = new Uint8Array(512);
        const micLevels = new Uint8Array(512);
        let loudnessSum = 0;
        let loudnessCount = 0;
        let lastSampleAt = 0;
        let talkingOverSince: number | null = null;
        let hasTalkedOver = false;

        levelWatchIntervalRef.current = setInterval(() => {
            const now = msIntoCall();
            const remote = remoteAnalyserRef.current;

            if (remote) {
                const isSpeaking =
                    rmsOf(remote, remoteLevels) > SPEECH_RMS_THRESHOLD;

                if (isSpeaking) {
                    consumerSpeechStartRef.current ??= now;
                    consumerQuietSinceRef.current = null;
                } else if (consumerSpeechStartRef.current !== null) {
                    consumerQuietSinceRef.current ??= now;

                    if (
                        now - consumerQuietSinceRef.current >=
                        CONSUMER_SILENCE_HOLD_MS
                    ) {
                        logEvent({
                            type: 'consumer_speech',
                            start: consumerSpeechStartRef.current,
                            end: consumerQuietSinceRef.current,
                        });
                        consumerSpeechStartRef.current = null;
                        consumerQuietSinceRef.current = null;
                    }
                }
            }

            const mic = micAnalyserRef.current;

            // The mic is off the call while the specialist is on, so the
            // server VAD can't see the trainee talk over them; read it here.
            if (mic && specialistOnLineRef.current && !hasTalkedOver) {
                if (rmsOf(mic, micLevels) > SPEECH_RMS_THRESHOLD) {
                    talkingOverSince ??= now;

                    if (now - talkingOverSince >= TALK_OVER_HOLD_MS) {
                        hasTalkedOver = true;
                        logEvent({
                            type: 'agent_talked_on_connect',
                            at: talkingOverSince,
                        });
                    }
                } else {
                    talkingOverSince = null;
                }
            }

            if (mic && agentSpeechStartRef.current !== null) {
                loudnessSum += rmsOf(mic, micLevels);
                loudnessCount++;

                if (now - lastSampleAt >= LOUDNESS_SAMPLE_MS) {
                    logEvent({
                        type: 'agent_loudness',
                        rms: Number((loudnessSum / loudnessCount).toFixed(4)),
                        at: now,
                    });
                    loudnessSum = 0;
                    loudnessCount = 0;
                    lastSampleAt = now;
                }
            }
        }, LEVEL_WATCH_INTERVAL_MS);
    }, [logEvent, msIntoCall]);

    const appendLine = useCallback(
        (
            speaker: LiveTranscriptLine['speaker'],
            text: string,
            at: number,
        ): number => {
            const line = {
                id: transcriptRef.current.length,
                speaker,
                text,
                at,
            };

            transcriptRef.current = [...transcriptRef.current, line].sort(
                (a, b) => a.at - b.at || a.id - b.id,
            );
            setTranscript(transcriptRef.current);

            return line.id;
        },
        [],
    );

    /**
     * The consumer's audio was cut before the line finished playing, or the
     * trainee talked over it. Recorded so the same one isn't counted
     * twice; the transcript line is left as it is.
     */
    const markCutOff = useCallback((itemId: string) => {
        cutOffItemsRef.current.add(itemId);
    }, []);

    const sendEvent = useCallback((event: Record<string, unknown>) => {
        if (dataChannelRef.current?.readyState === 'open') {
            dataChannelRef.current.send(JSON.stringify(event));
        }
    }, []);

    /** Puts the trainee's mic back on the call once the greeting is done. */
    const releaseMic = useCallback(() => {
        const heldMic = heldMicRef.current;

        if (!heldMic) {
            return;
        }
        heldMicRef.current = null;

        if (greetingMicTimeoutRef.current) {
            clearTimeout(greetingMicTimeoutRef.current);
            greetingMicTimeoutRef.current = null;
        }

        void heldMic.sender.replaceTrack(heldMic.track);
    }, []);

    const removeLine = useCallback((lineId: number) => {
        transcriptRef.current = transcriptRef.current.filter(
            (line) => line.id !== lineId,
        );
        setTranscript(transcriptRef.current);
    }, []);

    const stopGreetingWatch = useCallback(() => {
        if (greetingWatchIntervalRef.current) {
            clearInterval(greetingWatchIntervalRef.current);
            greetingWatchIntervalRef.current = null;
        }

        if (greetingCheckTimeoutRef.current) {
            clearTimeout(greetingCheckTimeoutRef.current);
            greetingCheckTimeoutRef.current = null;
        }
    }, []);

    /** Whether the consumer's audio is playing out of the element. */
    const isRemoteAudioPlaying = useCallback(() => {
        const remoteAudio = remoteAudioElRef.current;

        return !!remoteAudio && !remoteAudio.paused && !remoteAudio.muted;
    }, []);

    const stopAgentTurnWatch = useCallback(() => {
        if (agentTurnWatchIntervalRef.current) {
            clearInterval(agentTurnWatchIntervalRef.current);
            agentTurnWatchIntervalRef.current = null;
        }
    }, []);

    /**
     * The trainee talked over the consumer, who keeps talking. Logged once
     * per line for the barge-in score.
     */
    const recordTalkOver = useCallback(
        (itemId: string) => {
            markCutOff(itemId);
            logEvent({ type: 'consumer_interrupted', at: msIntoCall() });
        },
        [logEvent, markCutOff, msIntoCall],
    );

    /**
     * Ends the trainee's turn: real speech is committed (and answered once
     * it's transcribed), noise is cleared so the model never hears it.
     */
    const endAgentTurn = useCallback(() => {
        const turn = agentTurnRef.current;
        agentTurnRef.current = null;

        if (!turn) {
            return;
        }

        const startedAt = agentSpeechStartRef.current;
        agentSpeechStartRef.current = null;

        if (turn.loudMs < AGENT_TURN_MIN_LOUD_MS) {
            sendEvent({ type: 'input_audio_buffer.clear' });
            return;
        }

        let speechEvent: CallEvent | null = null;

        if (startedAt !== null) {
            speechEvent = {
                type: 'agent_speech',
                start: startedAt,
                end: turn.lastLoudAt,
            };
            logEvent(speechEvent);
        }
        nextCommitRef.current = { mayBeEcho: turn.mayBeEcho, speechEvent };
        sendEvent({ type: 'input_audio_buffer.commit' });
    }, [logEvent, sendEvent]);

    /**
     * Finds the trainee's turns from their mic level, all call, and counts
     * it as talking over the consumer once they're loud for long enough.
     */
    const startAgentTurnWatch = useCallback(() => {
        stopAgentTurnWatch();
        const mic = micAnalyserRef.current;

        if (!mic) {
            return;
        }

        const levels = new Uint8Array(512);
        const endSilenceMs =
            level >= PUSHY_MIN_LEVEL
                ? PUSHY_AGENT_TURN_END_SILENCE_MS
                : AGENT_TURN_END_SILENCE_MS;
        let startLoudMs = 0;
        let startQuietMs = 0;
        let firstLoudAt: number | null = null;
        let quietSince: number | null = null;
        let talkOverLoudMs = 0;
        let talkOverQuietMs = 0;
        const remoteLevels = new Uint8Array(512);
        const recentRemoteRms: number[] = [];
        let echoRatio = ECHO_RATIO_INITIAL;
        let firstLoudInEcho = false;
        let wasConsumerAudible = false;
        let lastEchoClearAt = 0;

        agentTurnWatchIntervalRef.current = setInterval(() => {
            const now = msIntoCall();
            const rms = rmsOf(mic, levels);

            // The consumer's loudest over the echo tail: their echo can
            // reach the mic a moment after they stop.
            const remote = remoteAnalyserRef.current;
            recentRemoteRms.push(remote ? rmsOf(remote, remoteLevels) : 0);

            if (
                recentRemoteRms.length >
                ECHO_TAIL_MS / AGENT_TURN_WATCH_INTERVAL_MS
            ) {
                recentRemoteRms.shift();
            }

            const remotePeak = Math.max(...recentRemoteRms);
            const isConsumerAudible = remotePeak > SPEECH_RMS_THRESHOLD;
            const turnThreshold = isConsumerAudible
                ? Math.min(
                      ECHO_GATE_MAX_RMS,
                      Math.max(
                          AGENT_TURN_RMS_THRESHOLD,
                          echoRatio * remotePeak * ECHO_MARGIN,
                      ),
                  )
                : AGENT_TURN_RMS_THRESHOLD;
            const isTraineeTalking =
                agentTurnRef.current !== null || startLoudMs > 0;

            // Learn how much of the consumer leaks into the mic, only while
            // the trainee isn't talking.
            if (isConsumerAudible && !isTraineeTalking) {
                echoRatio +=
                    (Math.min(ECHO_RATIO_MAX, rms / remotePeak) - echoRatio) *
                    ECHO_RATIO_SMOOTHING;
            }

            const recordingGain = micRecordingGainRef.current;

            if (recordingGain) {
                recordingGain.gain.setTargetAtTime(
                    isConsumerAudible && !isTraineeTalking
                        ? RECORDING_ECHO_DUCK_GAIN
                        : 1,
                    recordingGain.context.currentTime,
                    RECORDING_GAIN_TIME_CONSTANT_S,
                );
            }

            // Not on the call yet (greeting), or no longer (specialist).
            if (
                endingRef.current ||
                heldMicRef.current !== null ||
                micOffCallRef.current
            ) {
                endAgentTurn();
                startLoudMs = 0;
                firstLoudAt = null;
                wasConsumerAudible = isConsumerAudible;
                return;
            }

            // Keep the consumer's echo out of the model: clear it while
            // they talk, and once more when their echo tail ends.
            if (
                !isTraineeTalking &&
                (isConsumerAudible
                    ? now - lastEchoClearAt >= ECHO_CLEAR_MS
                    : wasConsumerAudible)
            ) {
                sendEvent({ type: 'input_audio_buffer.clear' });
                lastEchoClearAt = now;
            }
            wasConsumerAudible = isConsumerAudible;

            const isLoud = rms > turnThreshold;
            const turn = agentTurnRef.current;

            if (turn) {
                if (isLoud) {
                    turn.loudMs += AGENT_TURN_WATCH_INTERVAL_MS;
                    turn.lastLoudAt = now;
                } else if (now - turn.lastLoudAt >= endSilenceMs) {
                    endAgentTurn();
                    quietSince = now;
                }
            } else if (isLoud) {
                if (firstLoudAt === null) {
                    firstLoudAt = now;
                    firstLoudInEcho = isConsumerAudible;
                }
                startLoudMs += AGENT_TURN_WATCH_INTERVAL_MS;
                startQuietMs = 0;
                quietSince = null;

                if (startLoudMs >= AGENT_TURN_START_MS) {
                    agentTurnRef.current = {
                        loudMs: startLoudMs,
                        lastLoudAt: now,
                        mayBeEcho: firstLoudInEcho || isConsumerAudible,
                    };
                    agentSpeechStartRef.current = firstLoudAt;
                    startLoudMs = 0;
                    firstLoudAt = null;
                }
            } else {
                startQuietMs += AGENT_TURN_WATCH_INTERVAL_MS;

                if (startQuietMs >= AGENT_TURN_START_QUIET_RESET_MS) {
                    startLoudMs = 0;
                    firstLoudAt = null;
                }

                quietSince ??= now;

                if (now - quietSince >= AGENT_TURN_IDLE_CLEAR_MS) {
                    sendEvent({ type: 'input_audio_buffer.clear' });
                    quietSince = now;
                }
            }

            const itemId = playingItemIdRef.current;

            if (
                !itemId ||
                cutOffItemsRef.current.has(itemId) ||
                !isRemoteAudioPlaying()
            ) {
                talkOverLoudMs = 0;
                talkOverQuietMs = 0;
                return;
            }

            if (rms > Math.max(BARGE_IN_RMS_THRESHOLD, turnThreshold)) {
                talkOverLoudMs += AGENT_TURN_WATCH_INTERVAL_MS;
                talkOverQuietMs = 0;
            } else {
                talkOverQuietMs += AGENT_TURN_WATCH_INTERVAL_MS;

                if (talkOverQuietMs >= BARGE_IN_QUIET_RESET_MS) {
                    talkOverLoudMs = 0;
                }
            }

            if (talkOverLoudMs >= BARGE_IN_HOLD_MS) {
                talkOverLoudMs = 0;
                recordTalkOver(itemId);
            }
        }, AGENT_TURN_WATCH_INTERVAL_MS);
    }, [
        endAgentTurn,
        isRemoteAudioPlaying,
        level,
        msIntoCall,
        recordTalkOver,
        sendEvent,
        stopAgentTurnWatch,
    ]);

    /**
     * Drops a trainee turn that was the consumer's echo: it leaves the
     * conversation so the model never answers it, and its speech segment
     * leaves the delivery log.
     */
    const discardEchoTurn = useCallback(
        (itemId: string) => {
            const speechEvent = echoCheckItemsRef.current.get(itemId);
            echoCheckItemsRef.current.delete(itemId);
            sendEvent({ type: 'conversation.item.delete', item_id: itemId });

            if (speechEvent) {
                eventsRef.current = eventsRef.current.filter(
                    (event) => event !== speechEvent,
                );
            }

            logEvent({ type: 'echo_discarded', at: msIntoCall() });
        },
        [logEvent, msIntoCall, sendEvent],
    );

    /**
     * Asks the consumer to answer the trainee, after the line they're on
     * if they're still answering.
     */
    const requestReply = useCallback(() => {
        if (responseActiveRef.current) {
            replyPendingRef.current = true;
            return;
        }

        // Set now, not on `response.created`, so a second turn in between
        // waits instead of asking twice.
        responseActiveRef.current = true;
        sendEvent({
            type: 'response.create',
            event_id: `${REPLY_EVENT_ID_PREFIX}${Date.now()}`,
        });
    }, [sendEvent]);

    /**
     * Samples the consumer's audio level while the greeting plays. Without
     * a running AudioContext the level can't be read, and the greeting
     * counts as heard if the element is playing.
     */
    const startGreetingWatch = useCallback(() => {
        if (greetingWatchIntervalRef.current) {
            return;
        }

        const levels = new Uint8Array(512);

        greetingWatchIntervalRef.current = setInterval(() => {
            const greeting = greetingRef.current;
            const analyser = remoteAnalyserRef.current;
            const audioContext = audioContextRef.current;

            if (!greeting || !analyser || audioContext?.state !== 'running') {
                if (audioContext?.state === 'suspended') {
                    void audioContext.resume();
                }
                return;
            }

            greeting.couldMeasure = true;
            const rms = rmsOf(analyser, levels);
            greeting.peakRms = Math.max(greeting.peakRms, rms);

            if (rms > SPEECH_RMS_THRESHOLD && isRemoteAudioPlaying()) {
                greeting.heardMs += GREETING_WATCH_INTERVAL_MS;
            }
        }, GREETING_WATCH_INTERVAL_MS);
    }, [isRemoteAudioPlaying]);

    /** The consumer picks up the phone ("Hello?"). */
    const sendGreeting = useCallback(() => {
        greetingRef.current = {
            attempt: (greetingRef.current?.attempt ?? 0) + 1,
            itemId: null,
            heardMs: 0,
            couldMeasure: false,
            peakRms: 0,
            transcript: null,
        };
        startGreetingWatch();
        sendEvent({ type: 'response.create' });
        greetingMicTimeoutRef.current ??= setTimeout(
            releaseMic,
            GREETING_MIC_HOLD_MAX_MS,
        );
    }, [releaseMic, sendEvent, startGreetingWatch]);

    /**
     * Once the greeting has played: if it was heard, its line goes into the
     * chat and the mic goes on. Otherwise it's taken back out of the
     * conversation and asked for again, and after the last try the trainee
     * is asked to turn the audio on, which greets again. A level that
     * couldn't be read counts as not heard: the context is resumed and the
     * greeting retried rather than assumed.
     */
    const checkGreeting = useCallback(() => {
        if (greetingCheckTimeoutRef.current) {
            clearTimeout(greetingCheckTimeoutRef.current);
            greetingCheckTimeoutRef.current = null;
        }

        const greeting = greetingRef.current;

        // Wait for whatever is still being said to finish.
        if (!greeting || endingRef.current || responseActiveRef.current) {
            return;
        }

        const wasHeard =
            greeting.couldMeasure && greeting.heardMs >= GREETING_MIN_HEARD_MS;

        logEvent({
            type: 'greeting_checked',
            at: msIntoCall(),
            attempt: greeting.attempt,
            heard: wasHeard ? 1 : 0,
            heard_ms: greeting.heardMs,
            peak_rms: Number(greeting.peakRms.toFixed(4)),
        });

        if (import.meta.env.DEV) {
            // eslint-disable-next-line no-console
            console.debug('[roleplay-call] greeting checked', {
                ...greeting,
                wasHeard,
                isRemoteAudioPlaying: isRemoteAudioPlaying(),
                audioContextState: audioContextRef.current?.state,
            });
        }

        if (wasHeard) {
            greetingRef.current = null;
            stopGreetingWatch();

            if (greeting.transcript && greeting.itemId) {
                consumerLineIdsRef.current.set(
                    greeting.itemId,
                    appendLine(
                        'consumer',
                        greeting.transcript.text,
                        greeting.transcript.at,
                    ),
                );
            }

            releaseMic();
            return;
        }

        if (!greeting.couldMeasure) {
            void audioContextRef.current?.resume();
        }

        // eslint-disable-next-line no-console
        console.warn('[roleplay-call] greeting was not heard', {
            attempt: greeting.attempt,
            peakRms: greeting.peakRms,
        });

        if (greeting.itemId) {
            discardedItemsRef.current.add(greeting.itemId);
            sendEvent({
                type: 'conversation.item.delete',
                item_id: greeting.itemId,
            });

            const lineId = consumerLineIdsRef.current.get(greeting.itemId);

            if (lineId !== undefined) {
                removeLine(lineId);
            }
        }

        if (greeting.attempt < GREETING_MAX_ATTEMPTS) {
            remoteAudioElRef.current?.play().catch(() => {
                // The next check finds it still silent.
            });
            sendGreeting();
            return;
        }

        greetingRef.current = null;
        stopGreetingWatch();
        pendingGreetingRef.current = true;
        setAudioBlocked(true);
    }, [
        appendLine,
        isRemoteAudioPlaying,
        logEvent,
        msIntoCall,
        releaseMic,
        removeLine,
        sendEvent,
        sendGreeting,
        stopGreetingWatch,
    ]);

    const scheduleGreetingCheck = useCallback(
        (delayMs: number) => {
            if (greetingCheckTimeoutRef.current) {
                clearTimeout(greetingCheckTimeoutRef.current);
            }

            greetingCheckTimeoutRef.current = setTimeout(
                checkGreeting,
                delayMs,
            );
        },
        [checkGreeting],
    );

    /**
     * Retries the consumer's audio from a click, which browsers allow even
     * when they blocked autoplay. The greeting waits for this when it has
     * to, so the "Hello?" is never lost.
     */
    const enableAudio = useCallback(() => {
        const remoteAudio = remoteAudioElRef.current;

        if (!remoteAudio) {
            return;
        }

        void audioContextRef.current?.resume();
        remoteAudio
            .play()
            .then(() => {
                setAudioBlocked(false);

                if (pendingGreetingRef.current) {
                    pendingGreetingRef.current = false;
                    sendGreeting();
                }
            })
            .catch((playError: unknown) => {
                // eslint-disable-next-line no-console
                console.error('[roleplay-call] audio still blocked', playError);
            });
    }, [sendGreeting]);

    const cleanup = useCallback(() => {
        for (const timeout of [
            hangupTimeoutRef,
            transferTimeoutRef,
            greetingMicTimeoutRef,
            greetingCheckTimeoutRef,
            greetingWarmupTimeoutRef,
        ]) {
            if (timeout.current) {
                clearTimeout(timeout.current);
                timeout.current = null;
            }
        }
        stopRingbackRef.current?.();
        stopRingbackRef.current = null;
        specialistAudioRef.current?.pause();
        specialistAudioRef.current = null;
        window.speechSynthesis?.cancel();
        for (const interval of [
            elapsedIntervalRef,
            hangupWatchIntervalRef,
            levelWatchIntervalRef,
            greetingWatchIntervalRef,
            agentTurnWatchIntervalRef,
        ]) {
            if (interval.current) {
                clearInterval(interval.current);
                interval.current = null;
            }
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

        callInProgressRef.current = false;
        heldMicRef.current = null;
        consumerLineIdsRef.current.clear();
        cutOffItemsRef.current.clear();
        playingItemIdRef.current = null;
        cancelledByUsRef.current = false;
        agentTurnRef.current = null;
        micOffCallRef.current = false;
        replyPendingRef.current = false;
        micSenderRef.current = null;
        recordingDestinationRef.current = null;
        specialistOnLineRef.current = false;
        pendingGreetingRef.current = false;
        greetingRef.current = null;
        discardedItemsRef.current.clear();
        pendingTranscriptsRef.current.clear();
        echoCheckItemsRef.current.clear();
        nextCommitRef.current = null;
        micRecordingGainRef.current = null;
        awaitingFinalCommitRef.current = false;
        wiredRemoteStreamIdRef.current = null;
        remoteAnalyserRef.current = null;
        micAnalyserRef.current = null;
    }, []);

    /**
     * Resolves once the final commit is answered and every committed
     * trainee turn has its transcript, or after `FINAL_TRANSCRIPT_WAIT_MS`.
     */
    const waitForFinalTranscripts = useCallback(
        () =>
            new Promise<void>((resolve) => {
                const startedAt = Date.now();
                const poll = setInterval(() => {
                    const isSettled =
                        !awaitingFinalCommitRef.current &&
                        pendingTranscriptsRef.current.size === 0;

                    if (
                        isSettled ||
                        Date.now() - startedAt >= FINAL_TRANSCRIPT_WAIT_MS
                    ) {
                        clearInterval(poll);
                        resolve();
                    }
                }, FINAL_TRANSCRIPT_POLL_MS);
            }),
        [],
    );

    /**
     * Stops the call and keeps the recording in memory until the trainee
     * codes the call. The connection stays open a moment longer so the
     * trainee's last words still get transcribed.
     */
    const finishCall = useCallback(
        (reason: RoleplayEndReason) => {
            if (endingRef.current) {
                return;
            }
            endingRef.current = true;
            stopAgentTurnWatch();

            // Nothing more is heard or said: silence both directions, then
            // commit the turn the trainee was in the middle of, if any.
            localStreamRef.current?.getAudioTracks().forEach((track) => {
                track.enabled = false;
            });
            if (remoteAudioElRef.current) {
                remoteAudioElRef.current.muted = true;
            }
            if (
                dataChannelRef.current?.readyState === 'open' &&
                agentTurnRef.current
            ) {
                awaitingFinalCommitRef.current =
                    agentTurnRef.current.loudMs >= AGENT_TURN_MIN_LOUD_MS;
                endAgentTurn();
            }

            flushSpeech();

            const recordingStopped = new Promise<void>((resolve) => {
                const recorder = recorderRef.current;

                if (recorder && recorder.state !== 'inactive') {
                    recorder.onstop = () => resolve();
                    recorder.stop();
                } else {
                    resolve();
                }
            });

            void Promise.all([
                recordingStopped,
                waitForFinalTranscripts(),
            ]).then(() => {
                recordingRef.current = new Blob(recordedChunksRef.current, {
                    type: recordingTypeRef.current.mimeType,
                });
                cleanup();
                setEndReason(reason);
                setPhase('ended');
            });
        },
        [
            cleanup,
            endAgentTurn,
            flushSpeech,
            stopAgentTurnWatch,
            waitForFinalTranscripts,
        ],
    );

    /**
     * Ends the call once the consumer's current line has actually finished
     * playing, by watching the remote track's audio level. See
     * `use-realtime-call.ts` for why a fixed delay doesn't work.
     */
    const endAfterConsumerFinishes = useCallback(
        (reason: RoleplayEndReason) => {
            if (hangupWatchIntervalRef.current || hangupTimeoutRef.current) {
                return;
            }

            const analyser = remoteAnalyserRef.current;

            hangupTimeoutRef.current = setTimeout(
                () => finishCall(reason),
                HANGUP_SAFETY_TIMEOUT_MS,
            );

            if (!analyser) {
                return;
            }

            const levels = new Uint8Array(analyser.fftSize);
            let hasHeardSpeech = false;
            let silenceStartedAt: number | null = null;

            hangupWatchIntervalRef.current = setInterval(() => {
                if (rmsOf(analyser, levels) > HANGUP_SILENCE_RMS_THRESHOLD) {
                    hasHeardSpeech = true;
                    silenceStartedAt = null;
                    return;
                }

                if (!hasHeardSpeech) {
                    return;
                }

                silenceStartedAt ??= Date.now();

                if (Date.now() - silenceStartedAt >= HANGUP_SILENCE_HOLD_MS) {
                    finishCall(reason);
                }
            }, HANGUP_WATCH_INTERVAL_MS);
        },
        [finishCall],
    );

    const handleFunctionCall = useCallback(
        (call: FunctionCall) => {
            const args = parseArguments(call.arguments);
            const at = secondsIntoCall();
            const atMs = msIntoCall();

            switch (call.name) {
                case 'patience_changed': {
                    const next = Math.max(
                        0,
                        Math.min(startingPatience, Number(args.patience)),
                    );

                    if (!Number.isNaN(next)) {
                        setPatience(next);
                        logEvent({
                            type: 'patience_changed',
                            patience: next,
                            reason:
                                typeof args.reason === 'string'
                                    ? args.reason
                                    : '',
                            at: atMs,
                        });
                        appendLine(
                            'system',
                            `Patience ${next}/${startingPatience}${typeof args.reason === 'string' && args.reason ? ` · ${args.reason}` : ''}`,
                            at,
                        );
                    }
                    break;
                }
                case 'objection_raised':
                    logEvent({
                        type: 'objection_raised',
                        id: String(args.id),
                        at: atMs,
                    });
                    setOpenObjections((current) =>
                        current.includes(String(args.id))
                            ? current
                            : [...current, String(args.id)],
                    );
                    break;
                case 'objection_resolved':
                    logEvent({
                        type: 'objection_resolved',
                        id: String(args.id),
                        at: atMs,
                    });
                    setOpenObjections((current) =>
                        current.filter((id) => id !== String(args.id)),
                    );
                    break;
                case 'hang_up':
                    setPatience(0);
                    pendingEndReasonRef.current = 'hung_up';
                    appendLine('system', 'The consumer hung up.', at);
                    endAfterConsumerFinishes('hung_up');
                    break;
            }

            if (call.call_id) {
                sendEvent({
                    type: 'conversation.item.create',
                    item: {
                        type: 'function_call_output',
                        call_id: call.call_id,
                        output: JSON.stringify({ ok: true }),
                    },
                });
            }
        },
        [
            appendLine,
            endAfterConsumerFinishes,
            logEvent,
            msIntoCall,
            secondsIntoCall,
            sendEvent,
            startingPatience,
        ],
    );

    const handleServerEvent = useCallback(
        (event: Record<string, unknown>) => {
            const type = event.type as string | undefined;

            if (import.meta.env.DEV) {
                // eslint-disable-next-line no-console
                console.debug('[roleplay-call]', type, event);
            }

            if (type === 'error') {
                const error = event.error as
                    | { code?: string; event_id?: string }
                    | undefined;
                const code = error?.code;

                // A reply we asked for was refused: don't stay marked as
                // answering, or the consumer would never answer again. If
                // they already are, answer once that response is done.
                if (error?.event_id?.startsWith(REPLY_EVENT_ID_PREFIX)) {
                    if (code === ACTIVE_RESPONSE_ERROR_CODE) {
                        replyPendingRef.current = true;
                    } else {
                        responseActiveRef.current = false;
                    }
                }

                if (awaitingFinalCommitRef.current) {
                    awaitingFinalCommitRef.current = false;

                    if (code === EMPTY_COMMIT_ERROR_CODE) {
                        return;
                    }
                }

                // eslint-disable-next-line no-console
                console.error('[roleplay-call] server error', event);
                return;
            }

            const itemId =
                typeof event.item_id === 'string' ? event.item_id : null;

            if (type === 'input_audio_buffer.committed' && itemId) {
                const commit = nextCommitRef.current;
                nextCommitRef.current = null;
                awaitingFinalCommitRef.current = false;
                pendingTranscriptsRef.current.add(itemId);
                itemStartedAtRef.current.set(itemId, secondsIntoCall());

                // May be the consumer's echo: answered once its transcript
                // shows it isn't.
                if (commit?.mayBeEcho) {
                    echoCheckItemsRef.current.set(itemId, commit.speechEvent);
                    return;
                }

                spokeSinceAgentTurnRef.current = false;

                if (!endingRef.current && !pendingEndReasonRef.current) {
                    requestReply();
                }
                return;
            }

            if (type === AGENT_TRANSCRIPT_FAILED_EVENT) {
                if (itemId) {
                    pendingTranscriptsRef.current.delete(itemId);
                }

                // Nothing could be made of a turn that was probably echo.
                if (itemId && echoCheckItemsRef.current.has(itemId)) {
                    discardEchoTurn(itemId);
                    return;
                }
                // eslint-disable-next-line no-console
                console.warn('[roleplay-call] transcription failed', event);
                appendLine(
                    'agent',
                    '(inaudible)',
                    (itemId === null
                        ? undefined
                        : itemStartedAtRef.current.get(itemId)) ??
                        secondsIntoCall(),
                );
                return;
            }

            if (type && AGENT_TRANSCRIPT_EVENTS.has(type) && itemId) {
                pendingTranscriptsRef.current.delete(itemId);
            }

            // Once the call is ending, only the trainee's last transcripts
            // matter; anything the consumer starts saying was never heard.
            if (
                endingRef.current &&
                !(type && AGENT_TRANSCRIPT_EVENTS.has(type))
            ) {
                return;
            }

            // The consumer's audio was cut short (not by the VAD, which no
            // longer interrupts them); counted once with any talk-over.
            if (type === 'conversation.item.truncated') {
                // eslint-disable-next-line no-console
                console.warn('[roleplay-call] consumer cut off', event);

                if (itemId && !cutOffItemsRef.current.has(itemId)) {
                    markCutOff(itemId);
                    logEvent({
                        type: 'consumer_interrupted',
                        at: msIntoCall(),
                    });
                }
                return;
            }

            // WebRTC only: the consumer's audio finished playing (or was cut).
            if (
                type === 'output_audio_buffer.stopped' ||
                type === 'output_audio_buffer.cleared'
            ) {
                const playingItemId = playingItemIdRef.current;
                playingItemIdRef.current = null;

                // Cut mid-line without a truncation event: still mark it.
                if (
                    type === 'output_audio_buffer.cleared' &&
                    playingItemId &&
                    !cancelledByUsRef.current &&
                    !cutOffItemsRef.current.has(playingItemId)
                ) {
                    markCutOff(playingItemId);
                    logEvent({
                        type: 'consumer_interrupted',
                        at: msIntoCall(),
                    });
                }
                cancelledByUsRef.current = false;

                // The mic stays off until a greeting has been heard.
                if (greetingRef.current) {
                    scheduleGreetingCheck(GREETING_STOPPED_TAIL_MS);
                } else {
                    releaseMic();
                }
                return;
            }

            if (type === 'response.output_item.added') {
                const item = event.item as
                    | { id?: string; type?: string }
                    | undefined;

                if (item?.id) {
                    itemStartedAtRef.current.set(item.id, secondsIntoCall());

                    if (item.type === 'message') {
                        playingItemIdRef.current = item.id;

                        if (greetingRef.current) {
                            greetingRef.current.itemId ??= item.id;
                        }
                    }
                }
                return;
            }

            if (type === 'response.function_call_arguments.done') {
                handleFunctionCall(event as FunctionCall);
                return;
            }

            if (type === 'response.created') {
                responseActiveRef.current = true;
                return;
            }

            if (type === 'response.done') {
                responseActiveRef.current = false;

                const output =
                    ((event.response as { output?: { type?: string }[] })
                        ?.output as { type?: string }[] | undefined) ?? [];
                const calledTools = output.some(
                    (item) => item.type === 'function_call',
                );
                const spoke = output.some((item) => item.type === 'message');

                if (spoke) {
                    spokeSinceAgentTurnRef.current = true;
                }

                // Backstop for a greeting whose audio never starts or stops.
                if (greetingRef.current && !greetingCheckTimeoutRef.current) {
                    scheduleGreetingCheck(GREETING_DONE_GRACE_MS);
                }

                // The trainee spoke while the consumer was answering.
                if (replyPendingRef.current) {
                    replyPendingRef.current = false;

                    if (
                        !pendingEndReasonRef.current &&
                        !specialistOnLineRef.current
                    ) {
                        requestReply();
                        return;
                    }
                }

                // A tool-only response leaves the model waiting on our
                // outputs; ask it to carry on unless it just hung up or
                // already answered this turn (that's what made it repeat).
                if (
                    calledTools &&
                    !spoke &&
                    !spokeSinceAgentTurnRef.current &&
                    !pendingEndReasonRef.current
                ) {
                    requestReply();
                }
                return;
            }

            const text = (event.transcript as string | undefined)?.trim();

            if (!type || !text) {
                return;
            }

            const at =
                (itemId === null
                    ? undefined
                    : itemStartedAtRef.current.get(itemId)) ??
                secondsIntoCall();

            if (ASSISTANT_TRANSCRIPT_EVENTS.has(type)) {
                // A greeting that was never heard, transcribed late.
                if (itemId && discardedItemsRef.current.has(itemId)) {
                    return;
                }

                const greeting = greetingRef.current;

                // The transcript lands before the audio plays; the
                // greeting's line waits until it's been heard.
                if (greeting && itemId && greeting.itemId === itemId) {
                    greeting.transcript = { text, at };
                } else {
                    const lineId = appendLine('consumer', text, at);

                    if (itemId) {
                        consumerLineIdsRef.current.set(itemId, lineId);
                    }
                }

                // The consumer is talking but the trainee can't hear it:
                // the browser paused or blocked the audio. Retry, and ask
                // for a click if that fails.
                const remoteAudio = remoteAudioElRef.current;

                if (remoteAudio?.paused) {
                    remoteAudio.play().catch(() => setAudioBlocked(true));
                }

                if (awaitingSpecialistReplyRef.current) {
                    awaitingSpecialistReplyRef.current = false;
                    specialistOnLineRef.current = false;
                    logEvent({
                        type: 'lead_answered_specialist',
                        at: msIntoCall(),
                    });
                    setTransferStage('answered');
                }
                return;
            }

            if (AGENT_TRANSCRIPT_EVENTS.has(type)) {
                if (itemId && echoCheckItemsRef.current.has(itemId)) {
                    const recentConsumerLines = transcriptRef.current
                        .filter((line) => line.speaker === 'consumer')
                        .slice(-ECHO_TRANSCRIPT_RECENT_LINES)
                        .map((line) => line.text);

                    if (isEchoOfConsumer(text, recentConsumerLines)) {
                        discardEchoTurn(itemId);
                        return;
                    }

                    echoCheckItemsRef.current.delete(itemId);
                    appendLine('agent', text, at);
                    spokeSinceAgentTurnRef.current = false;

                    if (!endingRef.current && !pendingEndReasonRef.current) {
                        requestReply();
                    }
                    return;
                }

                appendLine('agent', text, at);
            }
        },
        [
            appendLine,
            discardEchoTurn,
            handleFunctionCall,
            logEvent,
            markCutOff,
            msIntoCall,
            releaseMic,
            scheduleGreetingCheck,
            secondsIntoCall,
            requestReply,
            sendEvent,
        ],
    );

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

    const endCall = useCallback(() => finishCall('agent'), [finishCall]);

    /**
     * Plays the specialist's line to the trainee (an element, so Chrome's
     * echo cancellation keeps it out of the mic) and into the recording.
     * The clip is preloaded when Transfer is clicked; the browser's speech
     * voice only reads the line if it still won't play. Resolves when it's
     * done.
     */
    const playSpecialistLine = useCallback(
        () =>
            new Promise<void>((resolve) => {
                const speakFallback = () => {
                    if (!window.speechSynthesis) {
                        resolve();
                        return;
                    }

                    const utterance = new SpeechSynthesisUtterance(
                        SPECIALIST_LINE,
                    );
                    utterance.onend = () => resolve();
                    utterance.onerror = () => resolve();
                    window.speechSynthesis.speak(utterance);
                };

                setTimeout(resolve, SPECIALIST_AUDIO_MAX_MS);

                const clip =
                    specialistAudioRef.current ??
                    new Audio(SPECIALIST_AUDIO_URL);
                specialistAudioRef.current = clip;
                clip.onended = () => resolve();
                clip.play().catch((playError: unknown) => {
                    // eslint-disable-next-line no-console
                    console.error(
                        '[roleplay-call] specialist clip failed',
                        playError,
                    );
                    speakFallback();
                });

                const audioContext = audioContextRef.current;
                const destination = recordingDestinationRef.current;

                if (audioContext && destination) {
                    void fetch(SPECIALIST_AUDIO_URL)
                        .then((response) => response.arrayBuffer())
                        .then((buffer) => audioContext.decodeAudioData(buffer))
                        .then((decoded) => {
                            const source = audioContext.createBufferSource();
                            source.buffer = decoded;
                            source.connect(destination);
                            source.start();
                        })
                        .catch(() => {
                            // The recording just won't have the specialist.
                        });
                }
            }),
        [],
    );

    /**
     * The specialist picks up after the fluff window. The trainee's mic
     * comes off the call (they should be silent now), the specialist asks
     * who they're speaking with, and the consumer answers.
     */
    const specialistJoins = useCallback(() => {
        if (endingRef.current) {
            return;
        }

        stopRingbackRef.current?.();
        stopRingbackRef.current = null;
        logEvent({ type: 'specialist_joined', at: msIntoCall() });
        setTransferStage('specialist');
        appendLine(
            'system',
            `Specialist: “${SPECIALIST_LINE}”`,
            secondsIntoCall(),
        );

        void micSenderRef.current?.replaceTrack(null);
        micOffCallRef.current = true;
        specialistOnLineRef.current = true;
        if (responseActiveRef.current) {
            cancelledByUsRef.current = true;
            sendEvent({ type: 'response.cancel' });
        }

        void playSpecialistLine().then(() => {
            if (endingRef.current || awaitingSpecialistReplyRef.current) {
                return;
            }

            awaitingSpecialistReplyRef.current = true;
            spokeSinceAgentTurnRef.current = false;
            sendEvent({
                type: 'conversation.item.create',
                item: {
                    type: 'message',
                    role: 'user',
                    content: [
                        { type: 'input_text', text: SPECIALIST_JOINS_MESSAGE },
                    ],
                },
            });
            sendEvent({ type: 'response.create' });
        });
    }, [
        appendLine,
        logEvent,
        msIntoCall,
        playSpecialistLine,
        secondsIntoCall,
        sendEvent,
    ]);

    /**
     * Cold transfer: it rings for `TRANSFER_FLUFF_MS` while the trainee
     * fluffs with the consumer, then the specialist joins. The trainee
     * completes the transfer themselves once both have spoken. When
     * Transfer was clicked is sent with the call so grading can check the
     * consumer said yes first.
     */
    const transfer = useCallback(() => {
        if (phase !== 'active' || endingRef.current) {
            return;
        }

        const at = secondsIntoCall();
        transferClickedAtRef.current = at;
        logEvent({ type: 'transfer_clicked', at: msIntoCall() });
        setPhase('transferring');
        setTransferStage('ringing');
        appendLine(
            'system',
            'Transferring… ringing the specialist. Keep them talking.',
            at,
        );

        sendEvent({
            type: 'conversation.item.create',
            item: {
                type: 'message',
                role: 'system',
                content: [
                    { type: 'input_text', text: TRANSFER_STARTED_MESSAGE },
                ],
            },
        });

        // Load the specialist's clip during the ringing so it's ready.
        const specialistClip = new Audio(SPECIALIST_AUDIO_URL);
        specialistClip.preload = 'auto';
        specialistClip.load();
        specialistAudioRef.current = specialistClip;

        const ringback = playRingback({
            rings: Math.ceil(TRANSFER_FLUFF_MS / RINGBACK_CYCLE_MS),
            volume: TRANSFER_RING_VOLUME,
        });
        stopRingbackRef.current = ringback.stop;
        transferTimeoutRef.current = setTimeout(
            specialistJoins,
            TRANSFER_FLUFF_MS,
        );
    }, [
        appendLine,
        logEvent,
        msIntoCall,
        phase,
        secondsIntoCall,
        sendEvent,
        specialistJoins,
    ]);

    /** "Leave Conference Call": the trainee drops off the transfer. */
    const completeTransfer = useCallback(() => {
        if (phase !== 'transferring' || endingRef.current) {
            return;
        }

        logEvent({ type: 'transfer_completed', at: msIntoCall() });
        appendLine('system', 'You left the conference.', secondsIntoCall());
        finishCall('agent');
    }, [appendLine, finishCall, logEvent, msIntoCall, phase, secondsIntoCall]);

    const start = useCallback(async () => {
        if (callInProgressRef.current) {
            return;
        }
        callInProgressRef.current = true;
        endingRef.current = false;
        setPhase('connecting');
        setError(null);

        try {
            // Mic first: the permission prompt can take a while, and the
            // ephemeral key is short-lived.
            // Explicit, so a browser default can't turn them off: echo of
            // the consumer or room noise reads as the trainee talking and
            // cuts the consumer off mid-sentence.
            const localStream = await navigator.mediaDevices.getUserMedia({
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl: true,
                },
            });
            localStreamRef.current = localStream;

            const sessionResponse = await fetch(
                createCallSession.url(sessionId),
                {
                    method: 'POST',
                    headers: { Accept: 'application/json', ...xsrfHeader() },
                    credentials: 'same-origin',
                },
            );

            if (!sessionResponse.ok) {
                throw new Error(
                    sessionResponse.status === 409
                        ? 'This call was already started. Start a new call.'
                        : `Could not start the call (HTTP ${sessionResponse.status}).`,
                );
            }

            const ephemeralKey = (
                (await sessionResponse.json()) as EphemeralSession
            ).value;

            if (!ephemeralKey) {
                throw new Error('Missing realtime session credentials.');
            }

            const pc = new RTCPeerConnection();
            peerConnectionRef.current = pc;
            localStream.getTracks().forEach((track) => {
                const sender = pc.addTrack(track, localStream);

                // Held off the call until the greeting has played, so the
                // trainee can't talk over it or have the VAD cut it off.
                if (track.kind === 'audio') {
                    micSenderRef.current = sender;
                    heldMicRef.current = { sender, track };
                    void sender.replaceTrack(null);
                }
            });

            // Created after the awaits above, so the browser may start it
            // suspended; the recording needs it running.
            const audioContext = new AudioContext();
            audioContextRef.current = audioContext;
            void audioContext.resume();
            const destination = audioContext.createMediaStreamDestination();
            recordingDestinationRef.current = destination;
            const micSource = audioContext.createMediaStreamSource(localStream);
            const micRecordingGain = audioContext.createGain();
            micSource.connect(micRecordingGain);
            micRecordingGain.connect(destination);
            micRecordingGainRef.current = micRecordingGain;

            const micAnalyser = audioContext.createAnalyser();
            micAnalyser.fftSize = 512;
            micSource.connect(micAnalyser);
            micAnalyserRef.current = micAnalyser;

            const remoteAudio = document.createElement('audio');
            remoteAudio.autoplay = true;
            // The consumer's voice must play through this element, not Web
            // Audio: Chrome's echo cancellation only covers element playback,
            // and without it the mic picks the consumer up as the trainee.
            // Attached (hidden) so it plays reliably; `cleanup()` removes it.
            remoteAudio.hidden = true;
            document.body.appendChild(remoteAudio);
            remoteAudioElRef.current = remoteAudio;

            const remotePlayback = remoteAudioPlayback(
                remoteAudio,
                pc,
                '[roleplay-call]',
            );

            pc.ontrack = (trackEvent) => {
                const remoteStream = trackEvent.streams[0];
                remoteAudio.srcObject = remoteStream;
                remotePlayback.play(trackEvent.track);

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
                setPhase('ringing');

                const ringback = playRingback();
                stopRingbackRef.current = ringback.stop;

                void Promise.all([
                    ringback.finished,
                    remotePlayback.started,
                ]).then(([, isPlaying]) => {
                    // Hung up or torn down while it was ringing.
                    if (dataChannelRef.current !== dataChannel) {
                        return;
                    }

                    recorder.start(1000);
                    callStartedAtRef.current = Date.now();
                    eventsRef.current = [];

                    // What the browser really applied to the mic, to
                    // diagnose echo: a constraint can be silently ignored.
                    const micSettings =
                        localStream.getAudioTracks()[0]?.getSettings() ?? {};
                    logEvent({
                        type: 'audio_checked',
                        at: 0,
                        echo_cancellation: micSettings.echoCancellation
                            ? 1
                            : 0,
                        noise_suppression: micSettings.noiseSuppression
                            ? 1
                            : 0,
                        auto_gain_control: micSettings.autoGainControl ? 1 : 0,
                    });
                    startLevelWatch();
                    startAgentTurnWatch();
                    setPhase('active');
                    elapsedIntervalRef.current = setInterval(
                        () => setElapsedSeconds(secondsIntoCall()),
                        1000,
                    );

                    // Only say hello once the trainee can hear it, and the
                    // output has had a moment to wake up after the ring.
                    if (isPlaying) {
                        greetingWarmupTimeoutRef.current = setTimeout(() => {
                            greetingWarmupTimeoutRef.current = null;

                            if (!endingRef.current) {
                                sendGreeting();
                            }
                        }, GREETING_WARMUP_MS);
                    } else {
                        pendingGreetingRef.current = true;
                        setAudioBlocked(true);
                    }
                });
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
                throw new Error(
                    `Could not connect to the call (HTTP ${sdpResponse.status}).`,
                );
            }

            await pc.setRemoteDescription({
                type: 'answer',
                sdp: await sdpResponse.text(),
            });
        } catch (caught) {
            cleanup();
            setError(
                caught instanceof Error
                    ? caught.message
                    : 'Could not start the call.',
            );
            setPhase('error');
        }
    }, [
        cleanup,
        handleServerEvent,
        logEvent,
        secondsIntoCall,
        sendGreeting,
        sessionId,
        startAgentTurnWatch,
        startLevelWatch,
    ]);

    /**
     * Uploads the coded call and returns the server's grade.
     */
    const complete = useCallback(
        async (disposition: string): Promise<RoleplaySessionResult> => {
            const formData = new FormData();
            formData.append(
                'transcript',
                transcriptRef.current
                    .filter((line) => line.speaker !== 'system')
                    .map(
                        (line) =>
                            `[${formatOffset(line.at)}] ${line.speaker}: ${line.text}`,
                    )
                    .join('\n'),
            );
            formData.append(
                'recording',
                recordingRef.current ?? new Blob(),
                `call.${recordingTypeRef.current.extension}`,
            );
            formData.append('disposition', disposition);
            formData.append('end_reason', endReason ?? 'agent');
            formData.append('events', JSON.stringify(eventsRef.current));

            if (transferClickedAtRef.current !== null) {
                formData.append(
                    'transfer_clicked_at',
                    String(transferClickedAtRef.current),
                );
            }

            const response = await fetch(completeCall.url(sessionId), {
                method: 'POST',
                body: formData,
                headers: { Accept: 'application/json', ...xsrfHeader() },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(
                    `Could not save the call (HTTP ${response.status}).`,
                );
            }

            return (await response.json()) as RoleplaySessionResult;
        },
        [endReason, sessionId],
    );

    useEffect(() => cleanup, [cleanup]);

    return {
        phase,
        muted,
        elapsedSeconds,
        patience,
        openObjections,
        transcript,
        endReason,
        error,
        audioBlocked,
        transferStage,
        start,
        enableAudio,
        toggleMute,
        endCall,
        transfer,
        completeTransfer,
        complete,
    };
}
