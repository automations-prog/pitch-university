# Roleplay: from mock call to real AI call

What has to change to turn the `/roleplay` page from a clickable mock into a live voice call against an AI
consumer, the same way the screening call already works (`screening/{token}` → OpenAI Realtime over WebRTC).

The two source docs stay the spec: `medicare_script.md` (what the trainee says) and
`OBJECTIONS_AND_PERSONAS.md` (who the consumer is). Nothing below changes their content.

---

## 1. Where we are now

| Piece                   | Today (mock, frontend only)                                   |
| ----------------------- | ------------------------------------------------------------- |
| Route                   | `GET /roleplay` → `Route::inertia('roleplay', 'roleplay/index')` |
| Persona                 | Built in the browser by `resources/js/lib/roleplay-persona.ts` |
| Script / objection data | Hand copy of the docs in `resources/js/lib/roleplay-data.ts`  |
| Consumer                | Canned lines picked from the persona, no AI                   |
| Trainee input           | Buttons: "Read step N", "Say this" on a rebuttal              |
| Patience                | Only drops when the trainee skips an open objection           |
| Grading                 | Trainee picks a disposition; compared to the persona's outcome |
| Storage                 | None; a refresh loses the call                                |

## 2. What we reuse from the screening call

| Screening piece                                             | Reuse for roleplay                                                        |
| ----------------------------------------------------------- | ------------------------------------------------------------------------- |
| `App\Services\OpenAiRealtimeClient::createEphemeralSession()` | Mint the ephemeral key. Needs to accept `instructions` + `tools` (see §4.3). |
| `App\Enums\RealtimeVoice`                                   | Pick the consumer's voice.                                                 |
| `ScreeningCallController@session` / `@complete`             | Same two-endpoint shape: mint session, then upload transcript + recording. |
| `StoreScreeningCallRequest`                                 | Same `recording` rules (`mimes:webm,wav,ogg,mp3,m4a`, `max:51200`).        |
| `resources/js/hooks/use-realtime-call.ts`                   | Copy the WebRTC plumbing: mic → peer connection → data channel → SDP exchange, recording via `MediaRecorder`, Safari-safe mime probing, transcript event matching, hang-up silence watch. |
| `config/services.php` → `openai.key`, `realtime_model`, `realtime_voice` | Same config. No new keys required.                                         |
| `tests/Feature/ScreeningCallTest.php`                       | Same test style with `Http::fake()`.                                       |

What is **different** from screening:

- Screening is a fixed, linear script the AI reads. Roleplay flips it: **the trainee reads the script and the AI
  improvises as the consumer**, inside the persona's rules.
- Screening is for guests via a token. Roleplay is for logged-in users, so sessions belong to a `User`.
- Screening sends its prompt from the browser (`session.update`). Roleplay must **not**, because the prompt holds
  the hidden outcome and DQ trap (see §4.3).

---

## 3. Backend

### 3.1 Database

New table `roleplay_sessions` (via `php artisan make:model RoleplaySession -mf`):

| Column             | Type                  | Notes                                               |
| ------------------ | --------------------- | --------------------------------------------------- |
| `id`               | id                    |                                                     |
| `user_id`          | foreignId, cascade    | Trainee                                             |
| `level`            | unsignedTinyInteger   | 1–5                                                 |
| `persona`          | json                  | Lead, objections, quirk, patience, DQ trap          |
| `expected_outcome` | string                | `transfer` / `dq` / `dnc`, hidden from the trainee  |
| `voice`            | string, nullable      | `RealtimeVoice` value used                          |
| `disposition`      | string, nullable      | What the trainee coded                              |
| `end_reason`       | string, nullable      | `agent` / `hung_up`                                 |
| `transcript`       | longText, nullable    | `role: text` lines, same format as `call_logs`      |
| `recording_path`   | string, nullable      | `public` disk, `roleplay-recordings/`               |
| `score`            | json, nullable        | Grading checklist results (§3.5)                    |
| `passed`           | boolean, nullable     |                                                     |
| `started_at`       | timestamp, nullable   | Set when the session is minted                      |
| `ended_at`         | timestamp, nullable   | Set on complete; also blocks a second complete      |
| timestamps         |                       |                                                     |

Model casts: `persona` and `score` → `array`, `started_at` / `ended_at` → `datetime`, `voice` → `RealtimeVoice`.
Add an `Outcome` enum (`Transfer`, `Dq`, `Dnc`) and cast `expected_outcome` to it.

### 3.2 Routes

Inside the existing `auth` + `verified` group in `routes/web.php`, replacing the current `Route::inertia` line:

```php
Route::prefix('roleplay')->name('roleplay.')->group(function () {
    Route::get('/', [RoleplayController::class, 'index'])->name('index');
    Route::post('sessions', [RoleplayController::class, 'store'])->name('sessions.store');
    Route::post('sessions/{roleplaySession}/call', [RoleplayCallController::class, 'session'])->name('call.session');
    Route::post('sessions/{roleplaySession}/call/complete', [RoleplayCallController::class, 'complete'])->name('call.complete');
});
```

Then `php artisan wayfinder:generate --with-form`. **Keep `--with-form`**: without it the app's `.form()` helpers
disappear and the other pages stop compiling.

### 3.3 Controllers

- **`RoleplayController@index`**: renders `roleplay/index` with the levels, script sections, rebuttals and
  compliance list as props (§3.6), plus the user's recent sessions.
- **`RoleplayController@store`** (`level` validated `1–5`): builds the persona **server-side** (§3.4), saves a
  `RoleplaySession`, returns only the public part: lead name/state/ZIP, level, quirk, starting patience. The
  objections, outcome and DQ trap never go to the browser.
- **`RoleplayCallController@session`**: authorizes the owner, `abort_if($session->ended_at !== null, 409)`, sets
  `started_at`, mints the ephemeral key **with the persona prompt baked in** (§4.3), returns it.
- **`RoleplayCallController@complete`** (`StoreRoleplayCallRequest`: `transcript`, `recording`, `disposition`
  validated against the disposition list, `end_reason`): stores everything, sets `ended_at`, runs grading (§3.5),
  returns the result for the wrap-up screen, including the now-revealed outcome and DQ trap.

Add a `RoleplaySessionPolicy` (`view`/`update` = owner) and use `Gate::authorize()` like `TrainingController`.
Cross-user access returns **404**, not 403.

### 3.4 Persona generator (port to PHP)

Move `resources/js/lib/roleplay-persona.ts` to `app/Services/RoleplayPersonaGenerator.php`, same rules:

- Objections unlocked where `min_level <= level`, drawn **without replacement, weighted by `weight`**.
- Objection count: random in the level's range (L1 `0–1`, L2 `1–2`, L3 `2–3`, L4 `3–4`, L5 `3–5`).
- Quirk at level 2+.
- Outcome drawn from the level mix (L1–2 100/0/0, L3–4 82/18/0, L5 61/14/25); DQ → one DQ trap.
- Patience `10 - level`.

Inject the random source so tests can seed it.

### 3.5 Grading

Run on `complete`, stored in `score`. Deterministic checks over the transcript first:

| Check                               | Source rule                                               |
| ----------------------------------- | --------------------------------------------------------- |
| Said "recorded line" + "America's Health" | Compliance: recorded line with America's Health     |
| Asked Parts A and B **and** double-confirmed the red, white and blue card | Compliance: double confirm |
| Confirmed state and ZIP             | Compliance                                                |
| Asked about work / VA insurance     | Compliance                                                |
| Said they're being transferred to a specialist | Compliance                                     |
| Got a **yes** after the transfer ask, **before** clicking Transfer | Compliance: the only permission in the call |
| Used "may" / "maybe" on benefit claims | Compliance: may and maybe are your best friends       |
| Disposition == expected outcome     | Persona outcome                                           |
| Didn't run out of patience          | Persona patience                                          |

`passed` = disposition correct **and** no hang-up **and** the transfer-permission check (for transfer outcomes).
An LLM grader for "stuck to the script" can come later as an extra check. It is not needed for v1.

### 3.6 One source for the docs content

Today the docs are copied by hand into `roleplay-data.ts`. The objection doc says rebuttals are "pulled live from
the script file", so:

- Add `app/Services/RoleplayScript.php` that parses `ai-roleplay/medicare_script.md` (sections by `##`,
  rebuttals by `**bold**` headers) and `OBJECTIONS_AND_PERSONAS.md` (the objection table, levels, DQ traps,
  quirks, outcome mix). Cache the result (`Cache::rememberForever`, cleared on deploy).
- Keep the objection → rebuttal-heading map in one PHP array (it's the only thing the docs don't state).
- Pass it to the page as props; delete the static arrays from `roleplay-data.ts` and keep only its types.
- Add a test that fails if a doc heading the parser depends on is renamed.

---

## 4. The AI consumer

### 4.1 Voice

Pick a `RealtimeVoice` per session (random, or matched to the lead name). Store it on the session.

### 4.2 Prompt: `app/Services/RoleplayConsumerPrompt.php`

Built from the persona and the docs, never from the browser. It must tell the model:

1. **Who it is**: an older Medicare member named `{lead name}` in `{state}`, `{zip}`, receiving an outbound
   call. Temperament from the level table, verbatim. Quirk, verbatim.
2. **What it raises**: the drawn objections with their consumer phrasings for the level, raised naturally over
   the call. `transfer_no` only at the transfer ask.
3. **How it answers**: truthfully when asked. It has Parts A & B, the red, white & blue card, and so on, unless
   the DQ trap says otherwise. The DQ trap's hidden truth is revealed **only when asked** (or volunteered).
4. **Outcome behavior**:
   - `transfer`: agrees to the transfer once objections are handled well.
   - `dq`: answers the qualifying question truthfully so the trainee must read the DQ line.
   - `dnc`: at some point says to stop calling / take them off the list.
5. **Swearing rule** (management rule 2026-09-14): swearing alone is an objection, not a DNC.
6. **Patience**: starts at `10 - level`. Loses a point when the trainee ignores an objection, reads the wrong
   rebuttal, rambles off script, or argues. At zero it hangs up.
7. **Never** break character, mention it's an AI or a test, or coach the trainee.

### 4.3 Session config (server-side)

Extend `OpenAiRealtimeClient::createEphemeralSession()` to accept optional `instructions` and `tools`, and put
them in the `client_secrets` request's `session` block. The browser then only opens WebRTC and never sees the
prompt.

Tools the model calls so the UI can react (function calling on the Realtime session):

| Tool                                  | UI effect                                                      |
| ------------------------------------- | -------------------------------------------------------------- |
| `patience_changed({ patience, reason })` | Updates the hearts                                          |
| `objection_raised({ id })`            | Lights "Objection open" on the rebuttals panel                  |
| `objection_resolved({ id })`          | Clears it                                                      |
| `hang_up({ reason })`                 | Ends the call as `hung_up` once the audio finishes (reuse the silence watch) |

> Same caveat as `use-realtime-call.ts`: verify event names, the `client_secrets` session shape and tool-call
> events against the current OpenAI Realtime docs before shipping. They have changed between versions.

Turn detection: `semantic_vad` with `eagerness: 'medium'`, the setting the screening call landed on after live
testing. At level 4–5 consider `high` so the consumer interrupts, matching "interrupts" in the temperament.

---

## 5. Frontend

### 5.1 New hook: `resources/js/hooks/use-roleplay-realtime-call.ts`

Copy of `use-realtime-call.ts` minus the screening script and interrupt timer, plus:

- Calls `roleplay.call.session` / `roleplay.call.complete` (Wayfinder).
- Does **not** send `instructions` in `session.update`, since they're already in the minted session.
- Handles the tool calls from §4.3 and replies with `function_call_output` so the model continues.
- Builds the transcript from the assistant / input transcription events (same fallback matching).
- Exposes `phase`, `muted`, `elapsedSeconds`, `patience`, `openObjection`, `transcript`, `start`, `toggleMute`,
  `endCall`, `transfer`.

### 5.2 UI changes

| Component            | Change                                                                          |
| -------------------- | ------------------------------------------------------------------------------- |
| `level-picker`       | "Start" posts to `roleplay.sessions.store`, then asks for the mic.               |
| `call-screen`        | Add a connecting state, mic mute, and live/ended from `phase`. Timer comes from the hook. |
| Conversation panel   | Live transcript. Replace "Read step N" with **Transfer** (enabled after the transfer ask) and **End call**. |
| `script-tracker`     | Stays a teleprompter. The trainee taps "Next" to move the highlight (no longer drives the consumer). |
| `rebuttals-panel`    | Reference only: remove "Say this". Keep search and the "Objection open" signal. |
| `wrap-up`            | Disposition picker posts with `complete`. Show the server's grading checklist instead of the self-check, plus a recording player. Restyle to match the call screen. |
| `use-roleplay-call.ts` | Delete once the real call ships (or keep behind a "practice without mic" toggle). |

### 5.3 Transfer flow (from the script)

1. Trainee reads the transfer ask, then **stops and waits**.
2. Consumer says yes → **Transfer** button enabled → click → simulated ringing, then an "agent" line
   ("Who am I speaking with?") → consumer answers → call ends as a transfer.
3. Consumer says no → the Transfer button stays disabled; the trainee must read an EXTRA NOT INTERESTED rebuttal
   and ask again.
4. Clicking Transfer without a yes is recorded and fails the permission check.

---

## 6. Tests (Pest, `tests/Feature/RoleplayCallTest.php`)

Modeled on `ScreeningCallTest.php`, with `Http::fake()` for OpenAI:

- Guest is redirected to login on every roleplay route.
- Starting a session saves a persona for the chosen level and returns **no** outcome, objections or DQ trap.
- `level` outside `1–5` fails validation.
- Minting a call session sends the persona prompt and tools to OpenAI (`Http::assertSent`).
- Minting uses the session's voice; falls back to `services.openai.realtime_voice`.
- A user can't mint or complete another user's session (404).
- Completing requires a recording and a valid disposition.
- Completing stores the transcript, recording (`Storage::fake('public')`) and disposition, and sets `ended_at`.
- A session can't be completed twice, or re-minted after completion (409).
- Grading: correct disposition + transfer permission passes; wrong disposition fails; transfer without a yes
  fails; hang-up fails.
- Persona generator (seeded): respects min level, count range, level 1–2 always transfer, DQ always has a trap.
- Script parser: fails loudly if a heading it depends on is renamed.

---

## 7. Build order

1. Migration, model, factory, `Outcome` enum, policy.
2. `RoleplayPersonaGenerator` (port) + tests.
3. `RoleplayScript` parser + props on `RoleplayController@index`; switch the frontend to props.
4. `RoleplayController@store` + tests.
5. `OpenAiRealtimeClient` accepts `instructions` / `tools`; `RoleplayConsumerPrompt`; `RoleplayCallController` + tests.
6. `use-roleplay-realtime-call.ts` and the UI changes; test live in the browser (mic, Safari).
7. Grading on `complete` + wrap-up screen.
8. Session history on the page; admin view in Reports (later).

## 8. Open questions

- **Recordings**: how long to keep them, and who can listen (trainee only, or admins too)?
- **Cost**: each call is a Realtime session. Cap sessions per user per day?
- **Level access**: stays free choice (decided), or unlock later using stored results?
- **Voice**: random per call, or matched to the lead's name?
- **Mock mode**: keep the current click-through as a "no mic" practice mode, or remove it?
