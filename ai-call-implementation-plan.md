# Implement the real AI voice call (replace the UI-only mock)

## Context

The candidate-facing "AI voice call" screen in `resources/js/pages/screening/show.tsx`
has been a pure UI mockup since it was built (connecting → active →
mute/end-call → thank you), with a static "Topic: Self Introduction" badge
and no actual audio, AI, or script logic. The data model to receive real
results (`app/Models/CallLog.php`: `called_at`, `transcript`,
`recording_path`, plus the manager scorecard/notes UI at
`resources/js/pages/admin/screening-responses/show.tsx`) was built as
prep and is sitting empty, waiting for a real integration to populate it.

`task1.md`'s script is fixed and linear (no branching), with one
hard-scripted beat: the AI must interrupt the candidate's 15-second pitch
with "why should I keep talking to you," timed off _when the candidate
starts speaking_, not off anything they say. Decisions made for this plan:
the call happens **in-browser** (candidate stays on the screening page,
grants mic access — no telephony/phone number involved), and the AI is a
**realtime speech-to-speech model** (OpenAI's Realtime API) rather than a
hand-built STT→state-machine→TTS pipeline — faster to build, natural
pause detection comes for free, at the cost of being an LLM that must be
tightly constrained to stay on-script and a per-call API cost.

This plan replaces the mock in `screening/show.tsx` with a real WebRTC
connection to OpenAI's Realtime API, enforces the script via a strict
system prompt plus an app-driven (not model-driven) interrupt timer, and
wires the results into the existing `CallLog` record.

## Approach

### 1. Config & secrets

- Add `OPENAI_API_KEY` to `.env`/`.env.example` and a `config/services.php`
  entry (`'openai' => ['api_key' => env('OPENAI_API_KEY')]`), matching how
  other third-party creds are configured in this app.
- **Verified against current OpenAI docs (2026-09-18)**, after the first
  live attempt 404'd on the initially-guessed endpoint:
    - Ephemeral credentials: `POST https://api.openai.com/v1/realtime/client_secrets`
      (not `/v1/realtime/sessions`, which no longer exists), body
      `{ "session": { "type": "realtime", "model": "gpt-realtime", "audio": { "output": { "voice": "..." } } } }`,
      response has the ephemeral key at the **top-level** `value` field (not
      nested under `client_secret.value`).
    - WebRTC SDP exchange: `POST https://api.openai.com/v1/realtime/calls`
      (not `/v1/realtime?model=...`) with the ephemeral key as the bearer
      token, raw SDP offer as the body, `Content-Type: application/sdp` —
      model is no longer a query param since it's fixed by the ephemeral
      session already.
    - `session.update`'s turn detection and input transcription config live
      nested under `session.audio.input.turn_detection` /
      `session.audio.input.transcription.model` (not top-level
      `turn_detection`/`input_audio_transcription`).
    - Default model bumped to `gpt-realtime` (the older
      `gpt-4o-realtime-preview` naming is stale).
    - **Still unverified** (no live session tested): the exact server event
      names for assistant/candidate transcript completion — these have
      reportedly moved between API versions. The implementation matches a
      set of known candidate event names plus dev-console logging of every
      unrecognized event so the real names can be confirmed on first live
      test and the code narrowed down afterward.

### 2. New public backend surface (not admin — candidate-facing, no auth)

New controller `App\Http\Controllers\ScreeningCallController`, routes added
next to the existing public `screening.show`/`screening.store` routes in
`routes/web.php` (same `{screening:token}` binding convention):

- `POST screening/{screening:token}/call/session` → `session()`: server-side
  call to OpenAI to mint a short-lived ephemeral client token for the
  Realtime API (never expose the real `OPENAI_API_KEY` to the browser).
  Guard: only allowed if `$screening->responses()->exists()` (candidate
  must have already submitted the form — mirrors the existing
  `alreadySubmitted` single-use guard in `ScreeningResponseController::store()`).
  Returns the ephemeral token + model id as JSON.
- `POST screening/{screening:token}/call/complete` → `complete()`: accepts
  the finished call's `transcript` (string) and an uploaded `recording`
  audio file (validated `mimes:webm,wav|max:...`), stores the audio via
  `Storage::disk('public')` (the only configured-but-unused disk suited for
  direct browser playback later — no upload/storage convention exists
  elsewhere in this app, so this establishes it), and updates the
  response's `CallLog` (`called_at`, `transcript`, `recording_path`) via
  `$screening->responses()->latest()->first()->callLog`.

No new policy needed (public, unauthenticated, scoped by token like the
existing screening routes) — but `complete()` must reject if `called_at`
is already set, so a candidate can't overwrite a finished call log by
replaying the request (same "single-use" spirit as the form).

### 3. Frontend: replace the mock in `resources/js/pages/screening/show.tsx`

Keep the existing visual shell (glass card, phases, mute/end-call buttons)
but back it with real state:

- **Connecting phase**: on `wasSuccessful`, call the new `call/session`
  endpoint, then establish WebRTC: `navigator.mediaDevices.getUserMedia`
  for the mic, `RTCPeerConnection` with the mic track attached, a data
  channel for Realtime API events, SDP offer/answer exchange with OpenAI
  using the ephemeral token, and attach the returned remote audio track to
  a hidden `<audio>` element for playback. Move to `active` once the
  connection + data channel are open.
- **Script enforcement**: on data-channel open, send a `session.update`
  event whose `instructions` field is the verbatim script from `task1.md`
  (opening line, ask-for-name/background, ask-for-15-second-pitch,
  closing line) with explicit instructions _not_ to improvise, branch, or
  reference that this is a test.
- **The interrupt beat (app-driven, not model-driven)**: listen for the
  Realtime API's server VAD events over the data channel
  (`input_audio_buffer.speech_started` / `speech_stopped`). When speech
  starts _after_ the AI's "give me a 15-second intro" turn has completed,
  start a ~10s timer; when it fires, send a `response.create` event
  instructing the assistant to immediately say the "why should I keep
  talking to you" line, interrupting whatever the candidate is doing —
  this keeps the interrupt a fixed, content-blind beat as the spec
  requires, rather than trusting the model to decide when to interrupt.
- **Transcript capture**: accumulate text from the Realtime API's
  transcript events (both the assistant's spoken turns and the candidate's
  transcribed speech) into an ordered array for the whole call.
- **Recording capture**: mix the local mic stream and the remote AI audio
  track into one stream via `AudioContext.createMediaStreamDestination()`,
  record it with `MediaRecorder` from call start, and stop it on end. This
  is the fiddliest part of the whole feature — flag it for extra manual
  testing (levels, dropped audio, browser codec support).
- **Ending the call** (natural script end or manual "End call" click):
  stop the recorder and the peer connection, then `POST` the joined
  transcript + recording blob to `call/complete` (multipart), and only
  then flip to the existing `ended` UI state.
- **Mute button**: make it real — toggle `track.enabled` on the local mic
  track instead of just swapping the icon.
- Update `resources/js/types/screening.ts` only if new props are needed
  from the controller (likely none — `token`/`alreadySubmitted` already
  cover what the page needs; the session/complete calls are plain fetches,
  not Inertia props).

### 4. What does _not_ change

- `app/Models/CallLog.php`, `CallLogResource`, `UpdateCallLogRequest`, the
  admin `CallLogController::update()`, and the entire
  `admin/screening-responses/show.tsx` scorecard/notes UI are untouched —
  they already read whatever lands in `called_at`/`transcript`/
  `recording_path`, so once `complete()` writes real data, the admin page
  just starts showing it.
- No changes to the public form (`FIELDS`, `ScreeningFormData`, `submit()`)
  or the single-use/`alreadySubmitted` logic.

### 5. Suggested build order (separate sessions, not one sitting)

1. Backend: config, `ScreeningCallController` (session mint + complete
   endpoint with file upload), routes, feature tests (mock the OpenAI
   HTTP call via `Http::fake()`; can't test WebRTC/audio in Pest).
2. Frontend: WebRTC connection + mic/audio plumbing only, no script logic
   yet — get "candidate can hear and talk to the AI" working end-to-end
   manually first.
3. Frontend: script enforcement (`session.update` instructions) + the
   timed interrupt beat.
4. Frontend: transcript accumulation + audio mixing/recording + the
   `complete()` upload on hangup.
5. Manual QA pass end-to-end, checking the admin detail page shows the
   real transcript/recording/timestamp.

## Verification

- New Pest feature tests for `ScreeningCallController`: session endpoint
  rejects when no response has been submitted yet, succeeds and returns a
  token shape when one exists (mock the outbound OpenAI call); `complete()`
  validates transcript/recording, updates the correct `CallLog`, and
  rejects a second call once `called_at` is already set.
- `npm run types:check`, `npm run check:fix`, `vendor/bin/pint --dirty --format agent`.
- Manual, in a real browser (not just automated tests): submit a
  screening form, grant mic permission, confirm you can hear/talk to the
  AI, confirm the interrupt fires ~10s into the pitch regardless of what
  you say, end the call, then check the admin response detail page shows
  a real timestamp, transcript, and a playable recording.
