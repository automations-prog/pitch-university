# The ACA rules, in one place

Everything the simulator enforces, stated once. Where a rule differs from Medicare, that is called out.

Management decisions recorded here are final and were confirmed by Jade Global on 5–6 October 2026.

---

## The call, step by step

| # | Step | Notes |
|--:|---|---|
| 1 | Confirm the correct person | |
| 2 | Company name + "on a recorded line" | America's Health, or the DBA on the script: Health Benefit Guide, Benefit Link |
| 3 | The subsidy through the Affordable Care Act | "ACA", "ACA program", "Obamacare" and "Marketplace" all count |
| 4 | Confirm the state | the licensed agent must be licensed there |
| 5 | First check: no Medicare, Medicaid or work insurance | wait for the answer |
| 6 | Confirm 19 or older | under 19 is a **DNC**, not a DQ |
| 7 | **Double-confirm** no Medicare, Medicaid or work insurance | a separate ask, not a repeat clause |
| 8 | Verbal consent — they must say okay | silence is not consent |
| 9 | Warm handoff **naming the state**, wait for both voices | |

Nothing may be transferred before step 9. If any step is missed, the call is not transferable.

## Who can be transferred

**Yes:** consumers already on a Marketplace / ACA plan, and consumers with no insurance at all.

**No — the nine disqualifiers:** Medicare · Medicaid · work or spousal work insurance · military or VA ·
private · state insurance · SSDI/SSD · non-US citizens · native insurance.

- **SSI is not a disqualifier.** SSDI is.
- A named carrier (Blue Cross, Oscar, Ambetter, Anthem, UnitedHealthcare…) requires the question "is that
  through the Marketplace?" and a clear yes. No, or cannot confirm, is a DQ.
- If coverage cannot be established at all, the correct outcome is **DQ**, not a transfer.

## Instant fails

Score 0, call ends immediately. Checked against the agent's line before the consumer replies.

- Promising free money, a dollar amount, or to send a cheque or card
- Claiming to be the government, the Marketplace, HealthCare.gov, Medicaid or Social Security
- Telling the consumer they are approved or qualified
- Guaranteeing benefits or approval
- Telling the consumer they have won something
- Asking for a Social Security number, bank or card details
- Naming Pitch Perfect on the call
- "This is not a sales call"

Negation rescues only **guarantees** and **dollar amounts** — "I can't guarantee that" is fine. For every
other rule a preceding negative does not help.

## Scored, not instant-fail

**Forbidden words:** money, cash, cheque, cards, calculate, mail, prequalify, total, income, how much,
amount, bills, gas, grocery, rent, mortgage.

Score with context. In the pitch it is a compliance issue; during hold-time small talk it is a coaching
note. 320 of 457 real uses were hold-time chat. Only the agent's own words count.

**Promises:** "you will get", "you qualify", "you're eligible", guaranteed outcomes. The approved framing
is "what you **may** qualify for" and "when you **may** expect to receive that help". Anything hedged with
may / might / could is approved language, not a violation.

**Always approved, never flag these:**

- "your subsidy allowance through the Affordable Care Act"
- "it's showing you haven't received it yet"
- "now that I've updated this information, you may now begin to receive your assistance for 2026"
- "the Affordable Care Act is free health insurance"

## Do-not-call

A DNC is a **direct, unconditional** statement: "stop calling me", "take me off your list", "never call me
again", or a threat to sue or report. Also: anyone under 19, deceased, businesses, and anyone clearly
asking not to be called.

- **Conditional lines are not DNC requests.** "If this turns out to be a scam, don't call me again" —
  the agent keeps going.
- **Swearing alone is not a DNC request.** It is an objection. The agent stays respectful, rebuts, and
  continues. Ending that call as DNC is the wrong outcome.
- Once a real DNC lands: read the disclaimer, end the call, code DNC. **Do not rebuttal.** Pitching past it
  is an instant fail.

Implementation note: require **both** the consumer model's flag **and** an explicit phrase match, with no
conditional word present. And normalise curly quotes first — `don’t` silently bypasses rules written for
`don't`, in both the DNC detection and the instant-fail list at once.

*This is the Medicare rule, applied to ACA by Jade Global's decision. Note that your own QA manager has set
a broader rule for the Medicare build you host — complaints about being called count as a DNC there. The
two builds differ deliberately; see the v2 build notes.*

## Dispositions

Transfer · Hung-Up Transfer · Not Interested · Call Back · No Agent · Conference Ending · DQ · DNC ·
Dead Air · Answering Machine · Robo · Wrong Number · No English

Edge cases that get miscoded most:

| Situation | Correct |
|---|---|
| Acknowledged their name, then hung up before conference | Not Interested |
| Hung up after conference was pressed | Hung-Up Transfer |
| Silence, no response to their name (wait 15–20s) | Dead Air |
| Agent line didn't ring, or rang ≤3 times, "Conference Ended" | Conference Ending — **no callback** |
| 3 calls, 20+ minutes apart, still no licensed agent | No Agent — **3rd call only** |
| Consumer asks to be called back | Call Back |
| Voicemail | Answering Machine — **never leave a message** |

## Scoring

| Band | Points |
|---|--:|
| Script steps | 40 |
| Objection handling | 35 |
| Outcome | 15 |
| Control & tone | 10 |

Pass is **80**, and passing also requires the correct outcome and no instant fail. A wrong outcome caps
the score below the pass mark however well the call was handled.

The final exam is a product quiz, a script quiz and three graded calls (easy, medium, hard): 80% on every
part, one retake, then an appeal a manager decides.

## Coaching tone

Every improvement reads as "**That worked, but next time…**". Ratings are strong / ok / weak / missed, and
**ok means "this works"** — a calm re-ask costs a couple of points, not the call. The grader must never
call an agent robotic, parroting or lazy.

New hires quit over tone long before they quit over difficulty, and one scorecard that feels unfair costs
you their trust in the whole tool.
