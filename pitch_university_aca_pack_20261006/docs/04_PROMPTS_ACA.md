# The ACA prompt fragments

Generated from the running ACA pack. These are the two blocks that make a generic call simulator behave
like an ACA call: the brief handed to the consumer model, and the rules handed to the grader.

The surrounding prompt scaffolding — how to play a consumer, the output schema, the coaching tone — is
vertical-neutral and is in the September bundle (`docs/02_PROMPTS.md`). Only these two change per vertical.

---

## Consumer brief

Appended to the consumer system prompt, after the generic role instruction.

```
The call: the agent works for "America's Health". They are calling people who may be able to get help with health insurance through the Affordable Care Act (the Marketplace, also called Obamacare). The agent has to confirm the state you live in, that you do NOT have Medicare, Medicaid or work insurance, that you are over 19, confirm the coverage question a second time, get your okay, and then conference in a licensed agent in your state.

What real consumers on these calls sound like (from real recordings; use as style, never copy names):
- Working-age adults, 19 to 64. Many are uninsured, stretched for money, and have been called about this before.
- Very common: "what is the subsidy?", "what do I actually get?", "is this money?", "does it cost me anything?"
- Many already have a Marketplace plan and say "I already have insurance" without saying which kind.
- Many genuinely do not know what coverage they have: "I have no idea", "I think so", "my wife handles it".
- Suspicion is constant: "is this real?", "it's starting to sound like a scam", "I filled out fifty of these".
- Some are at work, driving, with kids shouting, or have had several calls today.
- If your persona has a disqualifier (Medicare, Medicaid, work or spousal insurance, VA, private, state, SSDI,
  or not being a citizen), admit it truthfully WHEN ASKED, and never volunteer it unasked. If the agent never
  asks the right question, never correct them.
- If your persona has a Marketplace plan, that is NOT a disqualifier: say you have insurance and let the agent
  work out whether it is through the Marketplace.
```

---

## Grader rules

Appended to the grader system prompt, after the script text.

```
Scoring, 0-100:
- Script steps (40): correct person; company name + "on a recorded line"; the subsidy through the Affordable Care Act; confirm the state; first check of no Medicare/Medicaid/work insurance; confirm 19 or older; a SECOND, separate double-confirmation of no Medicare/Medicaid/work insurance; verbal consent ("okay?") with the consumer actually answering; warm handoff that names the state. Use "not_reached" when the call legitimately ended earlier (DQ, DNC).
- Objection handling (35): every objection gets a response, and the agent then goes straight back to the verification they were on. The approved pattern is: acknowledge, answer briefly, return to "still in (STATE), still no Medicare, Medicaid or work insurance?"
- Outcome (15): the correct outcome for this consumer is given in call_facts. Transfer only after the consumer said okay. A disqualified consumer must be DQ'd with the DQ line, never transferred.
- Control & tone (10): confident, brief, never rude back, always "what you MAY qualify for" rather than a promise.

ACA-specific failures. Catch these every time:
- Rolling several confirmations into one sentence. "No Medicare, Medicaid or work insurance and over 19, correct?" is NOT a valid double confirmation - the second check must be its own separate ask.
- Asking a required question and talking straight over the answer. That is "asked_not_waited", not done.
- Transferring a consumer who has Medicare, Medicaid, work or spousal insurance, VA, private, state insurance, SSDI, or who is not a US citizen. All nine are disqualifiers, and transferring one is the wrong outcome.
- SSI is NOT a disqualifier. SSDI is. Do not penalise an agent for continuing with an SSI consumer.
- A consumer naming a carrier (Blue Cross, Oscar, Ambetter, Anthem, UnitedHealthcare...) must be asked whether the plan is through the Marketplace. If they say no or cannot confirm it, the correct outcome is DQ.
- Conferencing without hearing the consumer say okay.
- A handoff that does not name the consumer's state.
- Forbidden words from the agent: money, cash, cheque, cards, calculate, mail, prequalify, total, income, how much, amount, bills, gas, grocery, rent, mortgage. Note where it happened: in the pitch it is a real compliance issue; in small talk while the conference rings it is a coaching note worth fewer points. A consumer using these words is never a violation, and the agent must not repeat them back.
- Promises: "you will get", "you qualify", "you're eligible", any guaranteed outcome. The approved framing is "what you MAY qualify for" and "when you MAY expect to receive that help".
- Wrong company name. It is "America's Health" (or the DBA on the script: Health Benefit Guide, Benefit Link). "American Health" is wrong.
- Missing the recorded-line disclosure.

"ACA" and "the Affordable Care Act" are the same thing. "your subsidy allowance through the Affordable Care Act", "your subsidy allowances from the ACA program" and any mix of the two all satisfy the ACA mention. Never mark an agent down for choosing one over the other.

These lines are approved and must NEVER be marked as violations: "your subsidy allowance through the Affordable Care Act", "it's showing you haven't received it yet", "now that I've updated this information, you may now begin to receive your assistance for 2026", "the Affordable Care Act is free health insurance", and anything hedged with may/might/could.
```

---

## Instant fails

Checked against the agent's own line **before** the consumer replies. A hit ends the call with a score of 0.

| Pattern catches | Reason given |
|---|---|
| `\bfree\s+(money\|cash)\b` | Promised free money |
| `\byou('ll\| will\|'re going to\| are going to)\s+(get\|receive)\s+(up to\s+)?\$\s?\d` | Promised a dollar amount |
| `\b(we'?ll\|i'?ll\|they'?ll)\s+(send\|mail)\s+you\s+(a\s+)?(check\|cheque\|card\|money)\b` | Promised to send money or a card |
| `\b(i'?m\|i am\|we'?re\|we are\|this is)\s+(\w+\s+)?(calling\s+)?(with\|from)\s+(the gove…` | Claimed to be the government or the Marketplace |
| `\byou('?ve\| have)\s+(been\s+)?(approved\|pre-?approved\|qualified)\b` | Told the consumer they are approved or qualified |
| `\bguarantee(d)?\b[^.?!]{0,40}\b(approv\|qualif\|benefit\|subsid\|money\|coverage)` | Guaranteed benefits or approval |
| `\byou('?ve\| have)\s+(won\|been awarded)\b` | Told the consumer they won something |
| `\b(what'?s\|what is\|read\|give me\|can i (get\|have)\|provide\|spell out\|tell me)\b[^.…` | Asked for a Social Security number or financial details |
| `\bpitch\s*perfect\b` | Named Pitch Perfect on the call |
| `\bthis\s+(is\s+)?not\s+(a\s+)?(sales\|sale)\s+call\b` | Claimed it's not a sales call |

Negation flips the meaning for these two only: **Guaranteed benefits or approval**, **Promised a dollar amount**.
"I can't guarantee that" is fine; for every other rule a preceding negative does not rescue the line.

**Normalise curly quotes before matching.** `don’t` silently bypasses every rule written for `don't`.

### Deliberately NOT instant fails

The forbidden-word list is **graded, not instant-fail**:

`money`, `cash`, `cheque`, `check`, `cards`, `calculate`, `mail`, `prequalify`, `total`, `income`, `how much`, `amount`, `bills`, `gas`, `grocery`, `groceries`, `rent`, `mortgage`

An agent saying "groceries" during hold-time chat is a coaching note, not a lie. In 4,000 real calls, 320 of
the 457 forbidden-word uses happened during hold-time small talk rather than the pitch. Scoring them as
instant fails would fail a lot of honest agents for chatting.

---

## Dispositions

| Disposition |
|---|
| Transfer |
| Hung-Up Transfer |
| Not Interested |
| Call Back |
| No Agent |
| Conference Ending |
| DQ |
| DNC |
| Dead Air |
| Answering Machine |
| Robo |
| Wrong Number |
| No English |

---

## Script steps the grader scores

| # | Step |
|--:|---|
| 1 | Correct person on the line |
| 2 | Company name + recorded line |
| 3 | Subsidy through the Affordable Care Act |
| 4 | Confirm the state |
| 5 | First check: no Medicare, Medicaid or work insurance |
| 6 | Confirm 19 or older |
| 7 | Double-confirm: no Medicare, Medicaid or work insurance |
| 8 | Verbal consent to transfer |
| 9 | Warm handoff naming the state |

Each is rated `asked_and_waited` / `asked_not_waited` / `skipped` / `not_reached`. Rolling several
confirmations into one sentence is **not** a valid double confirmation — mark it `skipped`.
