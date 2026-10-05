/**
 * Outbound ringback played before the AI picks up a call. It feels like a
 * real dial, and gives the remote WebRTC audio time to start flowing so the
 * AI's first line isn't clipped (it used to be lost entirely).
 */
const RINGBACK_FREQUENCIES_HZ = [440, 480];
const RINGBACK_TONE_MS = 1_800;
const RINGBACK_GAP_MS = 1_200;
const RINGBACK_RINGS = 2;
const RINGBACK_VOLUME = 0.08;

/** Longest to wait for the AI's audio to start playing before picking up anyway. */
export const REMOTE_AUDIO_WAIT_MS = 5_000;

/** How long one ring (tone plus gap) lasts. */
export const RINGBACK_CYCLE_MS = RINGBACK_TONE_MS + RINGBACK_GAP_MS;

/**
 * Plays a US-style ringback (440 + 480 Hz) on its own short-lived
 * AudioContext, closed once it's done, so the call's context never outputs
 * to the speakers and the ring stays out of the recording. `finished`
 * resolves when the ringing ends; `stop()` cuts it short (and never
 * resolves `finished`).
 */
export function playRingback({
    rings = RINGBACK_RINGS,
    volume = RINGBACK_VOLUME,
}: { rings?: number; volume?: number } = {}): {
    finished: Promise<void>;
    stop: () => void;
} {
    const audioContext = new AudioContext();
    void audioContext.resume();
    const startsAt = audioContext.currentTime;
    const durationMs = rings * RINGBACK_CYCLE_MS;
    let timeout: ReturnType<typeof setTimeout> | null = null;

    const close = () => {
        if (audioContext.state !== 'closed') {
            void audioContext.close();
        }
    };

    for (let ring = 0; ring < rings; ring++) {
        const toneStart = startsAt + (ring * RINGBACK_CYCLE_MS) / 1000;
        const toneEnd = toneStart + RINGBACK_TONE_MS / 1000;

        const gain = audioContext.createGain();
        gain.gain.setValueAtTime(0, toneStart);
        gain.gain.linearRampToValueAtTime(volume, toneStart + 0.02);
        gain.gain.setValueAtTime(volume, toneEnd - 0.02);
        gain.gain.linearRampToValueAtTime(0, toneEnd);
        gain.connect(audioContext.destination);

        for (const frequency of RINGBACK_FREQUENCIES_HZ) {
            const oscillator = audioContext.createOscillator();
            oscillator.frequency.value = frequency;
            oscillator.connect(gain);
            oscillator.start(toneStart);
            oscillator.stop(toneEnd);
        }
    }

    const finished = new Promise<void>((resolve) => {
        timeout = setTimeout(() => {
            close();
            resolve();
        }, durationMs);
    });

    return {
        finished,
        stop: () => {
            if (timeout) {
                clearTimeout(timeout);
            }
            close();
        },
    };
}

/**
 * Starts the remote audio element explicitly instead of trusting autoplay,
 * which can be blocked or late. Call `play()` from `pc.ontrack`; `started`
 * resolves `true` once it's playing, or `false` if the browser blocked it or
 * nothing played within `REMOTE_AUDIO_WAIT_MS`, so the call never stalls on
 * it. A blocked element needs `play()` again from a click.
 */
export function remoteAudioPlayback(
    audioElement: HTMLAudioElement,
    logTag: string,
): { play: () => void; started: Promise<boolean> } {
    let markStarted: (isPlaying: boolean) => void = () => {};
    const started = new Promise<boolean>((resolve) => {
        markStarted = resolve;
        setTimeout(() => resolve(!audioElement.paused), REMOTE_AUDIO_WAIT_MS);
    });

    return {
        started,
        play: () => {
            audioElement
                .play()
                .then(() => markStarted(true))
                .catch((playError: unknown) => {
                    // eslint-disable-next-line no-console
                    console.error(`${logTag} remote audio blocked`, playError);
                    markStarted(false);
                });
        },
    };
}
