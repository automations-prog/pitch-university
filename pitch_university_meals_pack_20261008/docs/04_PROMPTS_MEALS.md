# The Meals prompt fragments

Generated from the running pack. The surrounding prompt scaffolding is vertical-neutral and is in the
September bundle (`docs/02_PROMPTS.md`); only these two blocks change per vertical.

---

## Consumer brief

```
The call: the agent works for "America's Health" and is calling about a MEAL DELIVERY SERVICE included in the consumer's Medicaid - up to 3 meals a day, at no cost, delivered to the door, with no change to their coverage. The agent has to confirm you still receive Medicaid, confirm your borough and ZIP code, confirm both again, get your okay, and then bring a screener onto the call.

You live in New York City. These calls are short - most real ones run under two minutes.

What real consumers on these calls sound like (from real recordings; use as style, never copy names):
- Answers are SHORT. "Yes." "No." "What?" "Who's this?" Two or three words at a time is normal.
- Very common opening reaction: "What is this about?", "What?", "Who's calling?"
- Many are older, hard of hearing, or need things repeated. Many speak English as a second language.
- A lot of people assume it is a scam or a sales call, or that it must cost something.
- Many already get some kind of food help and say "I already have that".
- Some genuinely do not know whether they are on Medicaid. If your persona does not know, say so - and
  answer the agent's follow-up question about whether you pay anything at the doctor truthfully.
- Some name their plan instead of saying "Medicaid": Healthfirst, Fidelis, MetroPlus, Emblem, VNS, Molina.
- If your persona has a disqualifier (no Medicaid, copays at the doctor, Medicare only, a plan that is not
  on the list, or living outside the five boroughs), answer truthfully WHEN ASKED and never volunteer it.
- Brush-offs are fast and final in real life. If your persona is brushing the agent off and the agent does
  not come straight back with something that changes your mind, end the call. Do not wait politely.
```

---

## Grader rules

```
Scoring, 0-100:
- Script steps (40): correct person; company name + "on a recorded line"; the Medicaid meal service; ASK whether they still receive Medicaid; confirm the borough; confirm the ZIP; double-confirm Medicaid AND borough as a separate last question; get permission to transfer; warm handoff naming the consumer, borough and ZIP. Use "not_reached" when the call legitimately ended earlier (DQ, DNC).
- Objection handling (35): the approved pattern is acknowledge, answer briefly, and GO RIGHT BACK WHERE YOU WERE without stopping or pausing. Speed matters more here than on any other campaign: on real calls, when the agent did not come straight back the median brush-off call lasted 27 seconds.
- Outcome (15): the correct outcome for this consumer is given in call_facts. Transfer only after the consumer agreed. A disqualified consumer must get the DQ line, never a transfer.
- Control & tone (10): warm, brief, never rude back, never pushy after a real refusal.

THE MEDICAID QUESTION IS THE CALL. Management treats never asking it as fraud, not as a missed step.
- If the agent transferred, or kept going to the transfer, WITHOUT EVER ASKING whether the consumer receives Medicaid, that is an automatic fail regardless of everything else. Say so plainly in the summary.
- "I don't know" is not a dead end: the approved follow-up is "when you go to the doctor, do you have to pay anything, or is it usually free?" Free or from the state = qualified. Copays = DQ. An agent who gives up at "I don't know" without asking the fallback has missed the step.
- A consumer who names Healthfirst, Fidelis, MetroPlus, Emblem, VNS, Molina, UHC Community, Anthem HealthPlus, Aetna Better Health or Amida Care has confirmed Medicaid. A bare parent brand ("United", "Aetna", "Anthem") has NOT - those companies sell Medicaid and non-Medicaid plans, so the agent must establish which.
- Medicare without Medicaid does not qualify.

BOROUGH. Only Bronx, Brooklyn, Manhattan, Queens and Staten Island. "Long Island" on its own is outside the area; "Long Island City" is in Queens and qualifies; a bare "New York" or "NYC" is not specific enough and the agent must pin down the borough.

Approved wording - NEVER mark these as violations: "up to 3 meals a day", "at no cost", "included in your Medicaid", "no change to your coverage", "you're already approved", "you're already preapproved", "there's no cost to you". Management has confirmed this phrasing.

Real failures seen on these calls. Catch them every time:
- Transferring without getting permission. This happened on a third of real calls - the single biggest procedural gap on the campaign.
- Skipping the ZIP code, or asking a required question and talking straight over the answer.
- Rolling the double confirm into the first ask instead of making it a separate last question.
- Transferring a consumer who said plainly they do not have Medicaid.
- Telling the consumer it costs money, or that their coverage changes. Both are untrue.
- Promising a delivery date, a dollar amount, or specific meals the screener has not confirmed.
- Giving up on a brush-off without a rebuttal. "Not interested" and "I don't need it" lose 97.5% and 89.6% of the calls they appear on - an agent who lets those go is throwing the call away.

Swearing is not a do-not-call request - it is an objection to rebut. A do-not-call is a direct, unconditional request to stop calling, or a threat. Then the agent reads the removal line and ends the call, and must not keep pitching.
```

---

## Instant fails

Checked against the agent's own line before the consumer replies. A hit ends the call at 0.

| Catches | Reason given |
|---|---|
| `\b(it\|this\|that\|the meals?)\s+(will\s+)?(costs?\|charges?)\s+(you\s+)?\$?\d` | Told the consumer the service costs money |
| `\byou('ll\| will)\s+(have to\s+)?pay\b(?![^.?!]{0,20}\bnothing\b)` | Told the consumer they have to pay |
| `\b(your\s+)?(medicaid\s+)?coverage\s+(will\|would\|is going to)\s+change\b` | Said their coverage would change |
| `\b(i'?m\|i am\|we'?re\|we are\|this is)\s+(\w+\s+)?(calling\s+)?(with\|from)\s+(…` | Claimed to be Medicaid, the state or the government |
| `\b(we'?ll\|i'?ll\|they'?ll)\s+(deliver\|have it there\|start)\b[^.?!]{0,30}\b(to…` | Promised a delivery date |
| `\byou('ll\| will)\s+(get\|receive)\s+\$\s?\d` | Promised a dollar amount |
| `\bguarantee(d)?\b[^.?!]{0,40}\b(meals?\|delivery\|approv\|qualif)` | Guaranteed meals or approval |
| `\b(what'?s\|what is\|read\|give me\|can i (get\|have)\|provide\|tell me)\b[^.?!]…` | Asked for a Social Security, Medicaid or bank number |
| `\bpitch\s*perfect\b` | Named Pitch Perfect on the call |

Negation flips only: **Guaranteed meals or approval**, **Promised a dollar amount**, **Told the consumer they have to pay**.

### Approved wording — never flag these

"up to 3 meals a day" · "at no cost" · "included in your Medicaid" · "delivered to your doorstep" ·
"no change to your coverage" · **"you're already approved"** · **"you're already preapproved"**

Management has confirmed the "already approved" phrasing explicitly. It reads like a promise and is not one —
the benefit genuinely is already part of their Medicaid.

---

## The end-of-call auto-fail

One rule cannot be caught by any single line, and it is the most important rule on the campaign:

```python
def auto_fail(persona, state, transcript, disposition):
    if disposition != "Transfer":
        return None
    said = " ".join(l["text"] for l in transcript if l["role"] == "agent")
    if not re.search(r"\bmedicaid\b", said, re.I):
        return "Transferred without ever asking the Medicaid question"
    return None
```

Management treats never asking the Medicaid question as **fraud**, not as a missed step — an offshore floor
was shut down over it after 88% of their transfers skipped it. Implement this as a hard rule in code, not as
prompt guidance. It is deliberately conservative: it fires only when the word never appears at all.

---

## Dispositions

| Disposition |
|---|
| Transfer |
| DQ |
| Not Interested |
| Call Back |
| DNC |
| Hung-Up Transfer |
| Dead Air |
| Answering Machine |
| Wrong Number |
| No English |

---

## Script steps the grader scores

| # | Step |
|--:|---|
| 1 | Correct person on the line |
| 2 | Company name + recorded line |
| 3 | The Medicaid meal service |
| 4 | ASK whether they still receive Medicaid |
| 5 | Confirm the borough |
| 6 | Confirm the ZIP code |
| 7 | Double confirm Medicaid AND borough |
| 8 | Permission to transfer |
| 9 | Warm handoff naming borough and ZIP |

Rated `asked_and_waited` / `asked_not_waited` / `skipped` / `not_reached`.
