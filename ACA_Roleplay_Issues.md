# ACA Roleplay – Issue Findings

**Source:** Screen recording `Recording_2026-10-15_153552.mp4` (~16.5 min)
**App:** pitch-university → ACA Roleplay
**Note:** Analysis is based on what's on screen only. The audio narration could not be transcribed.

---

## Summary

Most of the problems are in the **grader**, not the trainee. In 3 of the 5 calls in this recording, the grading was wrong or unreliable, mostly because the trainee's speech wasn't being recorded.

## Calls Reviewed

| Call | Level | Result |
|---|---|---|
| Crystal Howard (OK) | 1 · Warm Up | Transfer completed |
| Jessica Ramirez (FL) | 2 · Guarded | **Failed**, even though it was coded correctly |
| Jordan Mitchell (LA) | 2 · Guarded | Transfer completed |
| Marcus Johnson (GA) | 3 · Skeptical | One check failed (forbidden words) |
| Crystal Howard (OK) | 4 · Hostile | Transfer (result screen not shown) |
| Crystal Howard (OK) | 5 · Nightmare | **Failed**, marked as coded wrong (DQ vs Not Interested) |

---

## Key Problems

### 1. The trainee's voice sometimes isn't captured (biggest problem)
- On the **Jessica Ramirez** call, the Conversation panel shows **only the customer's messages**. None of the trainee's lines appear.
- The customer still answers as if she heard everything ("Yeah, I can hear you. What's this about?", "Yeah, I said that"), and the call reached a transfer.
- The grader then failed almost every step:
  - Said you're with America's Health on a recorded line
  - Explained the subsidy through the Affordable Care Act
  - Confirmed the state (Florida)
  - Asked about Medicare, Medicaid, or work insurance
  - Confirmed they are 19 or older
  - Double-confirmed no Medicare, Medicaid, or work insurance
  - Heard them say okay before conferencing
  - Introduced them to the licensed agent, naming their state
- The result screen says: *"You coded it right, but the call failed on the compliance check."* Since the transcript has no trainee lines, the grader had nothing to score.
- The **level 4 Crystal Howard** call shows the same thing: only customer messages, even though she replies "Alright, what's next?"

### 2. A hang-up is graded wrong when the trainee was the one ending the call
- On the **level 5 Crystal Howard** call, the customer said she has a "state plan" and wouldn't confirm it was through the Marketplace.
- The trainee followed the script's rule (*"NO or cannot confirm = DQ"*) and read the disqualify close.
- The customer then said *"Oh, now you're letting me go? Fine, goodbye"* and hung up.
- The grader said the correct code was **Not Interested** because she "hung up before you conferenced." It marked the trainee's **DQ** as wrong.
- That hang-up came after the trainee disqualified her, so DQ should count. The rule "a hang-up is always Not Interested" needs an exception for hang-ups after a DQ.
- Her patience also dropped from 5/5 to 0 all at once when she hung up.

### 3. Forbidden-word flags may be false alarms
- On the **Marcus Johnson** call, the trainee failed: *"Kept forbidden words out of the pitch (you said: cheque, money)."*
- The Rebuttals panel's own heading is *"What am I going to get / Is it money, cash, cheque…"*. So either the trainee read that heading aloud, or the grader is matching words the customer said or the rebuttal text.
- This couldn't be confirmed without the audio, but it's worth checking.

### 4. Smaller issues
- **Script panel falls behind:** on the Marcus call and both later Crystal calls, the script stayed on step 1 while the conversation moved on, because the trainee wasn't clicking Next. If any scoring depends on the script step, it will be off.
- **Unclear rule for state plans:** a "state plan" isn't clearly covered by the DQ rule, since in some states the state's own exchange is the Marketplace. This deserves a specific rebuttal or rule.
- **Possible score mismatch:** Marcus's objection "What is the subsidy?" was scored *"Handled badly 8.5%,"* but the checklist passed *"Went back to the verification after each objection."* The two may not agree.

---

## Suggested Fixes

1. **Detect missing trainee audio.** When the trainee's side of the transcript is empty or missing, show a "mic not captured" warning instead of a compliance fail.
2. **Allow DQ after a hang-up.** If the trainee disqualified the customer before she hung up, accept DQ as the correct code.
3. **Narrow the forbidden-word filter.** Check it only against the trainee's own lines, not the customer's words or rebuttal headings.
4. **Keep the script in step.** Move the script forward automatically as the trainee speaks, or require clicking Next before Conference unlocks.
5. **Add a rule for state plans.** Add a specific rebuttal or DQ rule for customers who say they have a "state plan."
6. **Check objection scoring.** Make sure the objection score and the checklist item for returning to verification agree.

---

## Open Questions
- Was the trainee's microphone working throughout the Jessica Ramirez and level 4 Crystal calls? (An audio transcript would confirm this.)
- Did the trainee actually say "money" or "cheque" on the Marcus call?
- What did the level 4 Crystal call's result screen show? It isn't in the recording.


## other feedback
- Level 1 and 2 great
- love that theres minor objections
- level 3 can be a bit more tricky like mentioning insurances we actually deal with ( oscar, blue cross, ambetter)
- level 4 can be a call where 2 or 3 questions need to be asked
- level 5 did not make sense
- state plans are with medicaid and we cannot transfer those over