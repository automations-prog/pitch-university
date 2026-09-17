so what i want now is to integrate AI here for calls,

i will be sharing here the scripts for calls

Fixed, linear script. No branching logic, no conditional response trees, no time limit enforced on
the candidate — the AI should use natural pause detection to know when the candidate has
finished speaking.
[Call connects — no separate "this is a screening" preamble]
AI: "Hi, thanks for considering Pitch University and a career in
telephonic sales. Go ahead and tell me a little about yourself —
your name, where you're from, and any past experience with phone
sales or customer service, even if not any."
[Candidate responds — open-ended]
AI: "Got it, appreciate you sharing that. Now give me a quick
15-second intro like you're opening a real call."
[Candidate begins mock pitch]
AI (interrupts mid-pitch): "Hey, I gotta run in like ten seconds —
quick, why should I keep talking to you?"
[Candidate responds/recovers — AI does NOT explain this was a test,
does NOT soften it, does NOT offer a second attempt]
AI: "Thanks so much for your time — welcome to Pitch University,
and we'll follow up shortly on next steps."
[Call ends]
Key implementation notes: - The “why should I keep talking to you” interrupt must fire based
on turn-end/mid-speech detection, not on the content of what the candidate says — it is a fixed
beat in the sequence, not conditional logic. - It must be delivered with zero warning or setup —
nothing earlier in the script should telegraph that an interruption is coming. - Target total
runtime: 1–2 minutes, driven entirely by how long the candidate talks in the intro — there is no
filler content to pad time. - No scoring or pass/fail logic is executed by the AI itself during or
after the call.

Every completed call produces a log entry containing:
Candidate name/ID
Timestamp of call
Full transcript
Audio recording (playback)
Manager/HR notes field — free text, editable, tied to that specific candidate’s call so
notes never get lost or mixed up across candidates
This log is the system of record for the voice check stage — not a separate spreadsheet.

but for now dont integrate the AI stuff this is just a preparation for the implementation of AI
