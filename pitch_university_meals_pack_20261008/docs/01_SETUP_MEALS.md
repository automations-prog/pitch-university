# Setting up the Meals vertical

You have the simulator running for Medicare and have the ACA pack. Meals is the same engine again, with one
structural difference worth reading before anything else: **this campaign has a single question the whole
thing rests on, and skipping it is treated as fraud rather than as a missed step.**

---

## 1. What the call is

A meal delivery benefit **already included in the consumer's Medicaid** — up to 3 meals a day, no cost,
delivered to the door, no change to coverage. The agent is a fronter who confirms eligibility and
warm-transfers to a screener. Consumers are NYC Medicaid recipients across the five boroughs.

Four verifications, a permission, a handoff. A typical real call is about **90 seconds**.

| | Medicare | ACA | **Meals** |
|---|---|---|---|
| Qualifies on | has Parts A & B | has **no** coverage | has **Medicaid** |
| Geography | any state | any state | **5 NYC boroughs only** |
| Transfer | cold | warm | **warm, to a screener** |
| Call length | 3–5 min | 2–3 min | **~90 seconds** |

## 2. The rule to build first

```python
def auto_fail(persona, state, transcript, disposition):
    if disposition != "Transfer":
        return None
    said = " ".join(l["text"] for l in transcript if l["role"] == "agent")
    if not re.search(r"\bmedicaid\b", said, re.I):
        return "Transferred without ever asking the Medicaid question"
    return None
```

Management's position: transferring someone without ever asking whether they have Medicaid is **fraud**,
because it bills a transfer for a consumer nobody established as eligible. An offshore floor was shut down
over it after 88% of their transfers skipped the question.

Implement it in **code**, as an end-of-call check — no single line can catch it, and prompt guidance alone
will not hold. Keep it conservative: fire only when the word never appears at all.

## 3. The Medicaid branch is the heart of the vertical

The question has three answers and the middle one is where agents fall apart:

```
"I just need to confirm you ARE still receiving Medicaid, correct?"
  YES            -> continue
  NO             -> DQ line, end the call
  I DON'T KNOW   -> "when you go to the doctor, do you have to pay anything, or is it usually free?"
                      free / from the state -> continue
                      copays               -> DQ line, end the call
  names a plan   -> one of the ten qualifying plans -> continue
                    a bare parent brand             -> establish which plan
```

**Make roughly 1 in 5 personas answer "I don't know."** That matches the real calls, and the copay fallback
is the single most misplayed moment on this campaign. A simulator where every consumer answers cleanly
trains nobody for the one branch that actually costs transfers.

Ten plans count as Medicaid: Healthfirst, Fidelis, MetroPlus, Emblem, VNS, Molina, UHC Community,
Anthem HealthPlus, Aetna Better Health, Amida Care. A bare "United", "Aetna" or "Anthem" does **not** —
those companies sell Medicaid and ordinary plans alike. Medicare without Medicaid does not qualify.

## 4. Boroughs, with traps

Bronx · Brooklyn · Manhattan · Queens · Staten Island, and nothing else.

| Consumer says | Result |
|---|---|
| Long Island | Outside the area — DQ |
| **Long Island City** | **Queens — qualifies** |
| Yonkers, New Jersey, upstate | Outside the area — DQ |
| "New York" / "NYC" | Too vague — the agent must pin down the borough |

Give personas these from level 3. Long Island versus Long Island City is the one that catches people, in
both directions: DQ'ing someone who qualifies, and transferring someone who doesn't.

## 5. Weight objections by what they cost, not how often they happen

This is the first vertical where we could measure that, because the sample included **2,067 lost calls**
alongside 1,196 transfers.

| Objection | Comes up on | Calls lost |
|---|--:|--:|
| **Not interested** | 14.9% | **97.5%** |
| **I don't need it** | 8.5% | **89.6%** |
| **I'm busy / call me back** | 10.6% | **82.6%** |
| Who are you with? | 13.5% | 68.9% |
| **What is this?** | **19.8%** | 51.9% |

The most common objection is the safest. The brush-offs are less frequent and far more expensive — and on
**more than half** the "not interested" calls, no rebuttal was attempted at all.

```
weight = seen_pct * 10 + ended_badly_pct * 1.5
```

## 6. Speed is a trainable skill here, and the numbers prove it

When no rebuttal came, the median brush-off call ran **27 seconds**. When one did, **43**.

Two consequences for your build:

- **The consumer model must end calls fast.** If a persona is brushing the agent off and the agent does not
  come straight back with something that changes their mind, the consumer should hang up — not wait
  politely through a pause. Our consumer brief says this explicitly.
- **Grade the pause, not just the words.** The script says "GO RIGHT BACK WHERE YOU WERE, DON'T STOP OR
  PAUSE". An agent who delivers a perfect rebuttal after a three-second silence has already lost the call.

## 7. The procedural gap you will see immediately

| Step | Done properly | Asked, didn't wait | **Skipped** |
|---|--:|--:|--:|
| **Permission to transfer** | 47.8% | 19.9% | **32.3%** |
| ZIP confirmed | 56.6% | 18.7% | **24.7%** |
| Double confirm | 61.9% | 19.7% | 18.4% |
| Medicaid asked | 62.9% | **26.7%** | 10.4% |

A third of real calls were transferred with no permission at all. Worth knowing: the four top-performing
agents are at **49.8%** on that step — no better than the floor. It is a campaign-wide habit, not a
new-hire problem, so expect your trainees to be marked down on something their mentors also do.

## 8. Two gaps in the script itself

Flagged to Jade Global; no approved wording exists yet:

1. **"I was told I don't qualify"** — 1.7% of calls, loses 66% of them, and there is **no approved
   rebuttal**. Our course points agents at the closest approved line meanwhile.
2. **No written do-not-call disclaimer** on this script, unlike Medicare and ACA. Our simulator accepts a
   plain compliant removal statement.

If approved wording lands for either, it goes into `script.md` and flows through automatically — which is
the point of keeping the script file as the source of truth.

## 9. What's in the course

8 modules, 38 lessons, 70 quiz questions, 32 exam questions, about 2h50m:

| Module | Lessons | Covers |
|---|--:|---|
| The Benefit & Your Role | 5 | What the benefit is, what you may say, how short these calls are |
| The Medicaid Question | 5 | The branch, the copay fallback, the ten plans, the boroughs |
| The Script, Line by Line | 5 | Every step, with permission-to-transfer called out |
| Rebuttals — Where the Money Is | 5 | What each objection costs, the three brush-offs, the 27-second window |
| Compliance | 5 | The nine steps, the fraud line, what may and may not be said |
| Dispositions | 4 | The ten codes and the edge cases |
| What Real Calls Look Like | 5 | Won and lost side by side, the five gaps |
| The Convoso Dialer | 4 | Login, screen, transfer flow, coding |

## 10. Tests worth porting

These caught real bugs in our build:

- A transfer with no Medicaid question **fails**, regardless of everything else.
- Saying the Medicaid word anywhere clears that auto-fail.
- "You're already approved" / "preapproved" is **never** an instant fail — it is approved script wording.
- Telling the consumer it costs money, or that coverage changes, **is** an instant fail.
- Long Island City qualifies; Long Island does not.
- A disqualified consumer who gets transferred fails; reading the DQ line passes.
- The copay-fallback branch occurs often enough to practise (we assert >10% of personas).
- "If this is a scam don't call me again" is **not** a do-not-call request; swearing alone is not either.
- Every objection resolves to official wording from `script.md`, and no top-agent example line contains a
  consumer name or a banned claim.
