# The Meals rules, in one place

Everything the simulator enforces. Management decisions recorded here were confirmed by Jade Global on
7 October 2026.

---

## The call, step by step

| # | Step | Notes |
|--:|---|---|
| 1 | Confirm the correct person | |
| 2 | Company name: **America's Health** | never Medicaid, never the state, never Pitch Perfect |
| 3 | "on a recorded line" | missing from 4.4% of real calls |
| 4 | The **Medicaid meal service** | say what the call is about early |
| 5 | **ASK whether they still receive Medicaid** | never asking = automatic fail |
| 6 | Confirm the **borough** | five boroughs only |
| 7 | Confirm the **ZIP** | skipped on 24.7% of real calls |
| 8 | **Double confirm** Medicaid AND borough | a separate last question |
| 9 | **Permission to transfer** — they must say okay | skipped on 32.3% of real calls |
| 10 | Warm handoff naming consumer, borough and ZIP | |

## Qualifying

**Medicaid** — the agent must ask. Three qualifying routes:

1. A direct yes.
2. "I don't know" → **"when you go to the doctor, do you have to pay anything, or is it usually free?"**
   Free or from the state qualifies. Copays is a DQ.
3. Names one of: Healthfirst · Fidelis · MetroPlus · Emblem · VNS · Molina · UHC Community ·
   Anthem HealthPlus · Aetna Better Health · Amida Care.

Not qualifying: a bare parent brand (United, Aetna, Anthem, Cigna, Humana), Medicare without Medicaid,
copays at the doctor, or no Medicaid at all.

**Borough** — Bronx, Brooklyn, Manhattan, Queens, Staten Island. Long Island is outside the area;
Long Island City is Queens and qualifies; a bare "New York" must be resolved.

## Instant fails

- Telling the consumer it costs money, or that they have to pay
- Saying their coverage will change
- Claiming to be Medicaid, Medicare, the state or the government
- Promising a delivery date
- Promising a dollar amount
- Guaranteeing meals or approval
- Asking for a Social Security, Medicaid or bank number
- Naming Pitch Perfect

Negation rescues only guarantees, dollar amounts and "you have to pay" — "you don't pay anything" is fine.

## The end-of-call auto-fail

**Transferring without ever asking the Medicaid question.** Treated as fraud, not as a missed step.
Implement in code as an end-of-call check; see `01_SETUP_MEALS.md` §2.

## Approved wording — never flag

"up to 3 meals a day" · "at no cost" · "included in your Medicaid" · "delivered to your doorstep" ·
"no change to your coverage" · **"you're already approved"** · **"you're already preapproved"** ·
"new varieties have been released"

Management confirmed the "already approved" phrasing explicitly on 7 October. It reads like a promise and
is not one — the benefit genuinely is already part of their Medicaid.

## Do-not-call

A direct, unconditional request to stop calling, or a threat. Conditionals are not DNC requests
("if this is a scam don't call me again"). **Swearing alone is not a DNC request** — it is an objection to
rebut. Once a real DNC lands: state they will be removed, end the call, code DNC, and never keep pitching.

Require both the consumer model's flag and an explicit phrase match, and normalise curly quotes before
matching — `don't` written with a curly apostrophe silently bypasses rules written for the straight one.

*Note: this script has no written DNC disclaimer, unlike Medicare and ACA. Flagged to Jade Global.*

## Dispositions

Transfer · DQ · Not Interested · Call Back · DNC · Hung-Up Transfer · Dead Air · Answering Machine ·
Wrong Number · No English

| Situation | Correct |
|---|---|
| Acknowledged their name, then hung up before the transfer | Not Interested |
| Hung up after the transfer started | Hung-Up Transfer |
| No Medicaid / copays / wrong plan / Medicare only / outside the boroughs | DQ |
| Voicemail | Answering Machine — **never leave a message** |
| Someone else answers, lead not there | Wrong Number — do not pitch them |

**169 consumers in the sample should have been DQ'd. 99 were caught. 38 were transferred anyway.**
A DQ is a correct outcome and should be scored as one.

## Scoring

| Band | Points |
|---|--:|
| Script steps | 40 |
| Objection handling | 35 |
| Outcome | 15 |
| Control & tone | 10 |

Pass is 80, and passing also requires the correct outcome and no instant fail. Grade the **pause** as well
as the words: on this campaign a rebuttal delivered after a silence has already lost the call.

## Coaching tone

"That worked, but next time…" rather than "Missed". Ratings strong / ok / weak / missed, where **ok means
this works**. Never call an agent robotic, parroting or lazy. One scorecard that feels unfair costs you the
agent's trust in the tool — and on a 90-second call there is very little room to be fair about afterwards.
