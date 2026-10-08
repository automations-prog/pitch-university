# Setting up the ACA vertical

You already have the simulator running for Medicare. ACA is the same engine with a different data pack —
but the qualifying logic runs in the **opposite direction**, so read §1 before anything else.

Everything you need is in this bundle. Nothing here requires access to Jade Global's systems.

---

## 1. The one thing that will catch you out

| | Medicare campaign | **ACA campaign** |
|---|---|---|
| You want | people who **have** Medicare Parts A & B | people who have **no** Medicare, **no** Medicaid, **no** work insurance |
| Medicare means | qualified | **disqualified** |
| Typical age | 65+ | **19–64** |
| Already covered? | fine | fine **only** if it's a Marketplace plan |

Any persona logic, grading rule or test you carried over from Medicare that treats coverage as a positive
signal will be wrong here. Coverage is a disqualifier on this campaign.

## 2. What to swap in

| Your component | Replace with |
|---|---|
| Script source of truth | `content/script.md` |
| Course modules, lessons, quizzes, exam pools | `content/course.json` |
| Objection library | `knowledge_base/aca_objections.json` |
| Consumer prompt brief | `docs/04_PROMPTS_ACA.md` § Consumer brief |
| Grader rules | `docs/04_PROMPTS_ACA.md` § Grader rules |
| Instant-fail list | `docs/04_PROMPTS_ACA.md` § Instant fails |
| Dispositions | `docs/04_PROMPTS_ACA.md` § Dispositions |
| Persona builder fields | `knowledge_base/OBJECTIONS_AND_PERSONAS.md` § Persona fields |
| Voice casting | `docs/03_VOICE_CASTING_ACA.md` |

Everything that does **not** change: the turn loop, the two-prompt structure, the state machine, async
grading, the score assembly, the budget cap, and the voice plumbing. That is the point of keeping the
engine vertical-neutral — in our build, adding ACA touched one new data file and a lookup, not the engine.

## 3. The script file is the source of truth

No rebuttal wording is hardcoded anywhere. At startup, the objection library reads `script.md`, matches
each objection id to a rebuttal heading, and pulls the official wording from it.

```
SCRIPT_HEADINGS = {
  "not_interested":  "I'm not interested / I don't want that",
  "what_is_subsidy": "What is the subsidy / Any questions in regards to the subsidy",
  "scam":            "This is a scam / spam call / That doesn't sound true",
  ...
}
```

Each objection in `aca_objections.json` carries its `script_heading`, so the mapping ships with the data.

**Why it matters:** when management changes a word in the script, the consumer model, the grader, the
coaching panel and the "better line" on the scorecard all change with it, with no deploy. Two requirements
follow: the headings must stay exactly as written, and a malformed script file must never take the app
down — keep inline fallbacks.

## 4. Nine disqualifiers, and the two traps inside them

Medicare · Medicaid · work or spousal work insurance · military/VA · private · state insurance ·
SSDI/SSD · non-US citizens · native insurance.

- **SSI is not a disqualifier. SSDI is.** They sound almost identical on a call and mean opposite things.
- **A named carrier is not an answer.** Blue Cross, Oscar, Ambetter, Anthem and UnitedHealthcare all sell
  Marketplace plans *and* other plans. The agent must ask "is that through the Marketplace?" and get a
  clear yes. No, or unsure, is a DQ.

In our personas, a consumer with a disqualifier admits it **only when asked**, and never volunteers it. If
the agent never asks the right question, the consumer never corrects them — which is exactly how the real
mistake happens.

## 5. The two steps that matter most

From 4,000 analysed real calls, these are the most-skipped requirements:

| Step | Skipped on | Also rushed on |
|---|--:|--:|
| **Double-confirm no Medicare/Medicaid/work insurance** | 33.8% | 13.1% |
| **Verbal consent to transfer** | 18.9% | 16.2% |

Two grading rules follow, and both are worth implementing exactly:

1. **Rolling confirmations into one sentence is not a double confirmation.** "No Medicare, Medicaid or work
   insurance and over 19, correct?" asked once scores as *skipped*. The second check must be its own ask,
   after the first was answered.
2. **Asked-but-not-waited is its own rating**, distinct from done and from skipped. On roughly a quarter of
   real calls an agent asks a required question and talks over the answer. It counts as asked and verifies
   nothing, and agents cannot fix a habit nobody names.

## 6. Decide the outcome at grading time, not at persona creation

This is the defect your QA manager hit on the Medicare build, and it will bite here too if the persona's
`correct_outcome` is treated as fixed.

Build the persona with an intended outcome, then **recompute what is acceptable at the end of the call**
from what actually happened:

```
consumer revealed a disqualifier      -> DQ is correct (transfer fails, however smooth the call was)
consumer made a real DNC request      -> DNC is correct
consumer hung up after agreeing       -> Hung-Up Transfer is correct
consumer hung up before agreeing      -> Not Interested is correct
consumer agreed and was transferred   -> Transfer is correct
```

Then hand the result to the grader as a **fact**, not something to infer. Outcome judgement was the single
biggest source of unfair scores in our build, and unfair scores are what stop agents trusting the tool.

## 7. Forbidden words are graded, not instant-fail

The ACA script bans a list of words: money, cash, cheque, cards, calculate, mail, prequalify, total,
income, how much, amount, bills, gas, grocery, rent, mortgage.

Do **not** make these instant fails. In 4,000 real calls, **320 of 457 uses happened during hold-time small
talk** — agents chatting about the price of groceries while the conference rang — not in the pitch. Score
them with their context: in the pitch it is a compliance issue, on hold it is a coaching note.

Two details that save you a bug report each:

- **Only the agent's words count.** A consumer asking "is this money?" is not a violation. The agent
  repeating it back is. Identify the agent's channel first and attribute every quote.
- **Hedged language is the approved framing, not a promise.** "Benefits you *may* receive" is correct. Our
  first automated pass flagged 1,260 promise violations; after excluding hedged script language, 6 were
  real. If your grader is flagging a lot of promises, check this first.

## 8. "ACA" and "the Affordable Care Act" are the same thing

Both wordings satisfy the ACA mention. So do "ACA program", "Obamacare" and "Marketplace". Management has
confirmed this explicitly — never mark an agent down for which one they chose.

## 9. What's in the course

8 modules, 41 lessons, 88 quiz questions, 34 final-exam questions, about 3 hours 20 minutes:

| Module | Lessons | Covers |
|---|--:|---|
| The ACA & Your Role | 5 | What the ACA is, how it inverts Medicare, what the subsidy may and may not be called |
| Who Qualifies, Who Doesn't | 4 | The two transferable groups, the nine DQs, SSDI vs SSI, the carrier check |
| The Script, Line by Line | 6 | Every step in order, with the two most-skipped called out |
| Rebuttals That Work | 6 | The loop pattern, the real objections with real frequencies |
| Compliance & Forbidden Words | 5 | Ten required steps, the promise rules, both word lists, company name |
| Dispositions | 5 | All 13, with the edge cases that get miscoded |
| What Real Calls Look Like | 5 | Patterns from 4,000 recorded transfers |
| The Convoso Dialer | 5 | Login, the agent screen, transfer flow, scheduled callbacks |

The final exam draws 20 product questions and 8 script questions, 80% to pass on each.

## 10. Sanity checks before you let an agent near it

Port these as tests; they are the ones that caught real bugs in our build:

- A disqualified consumer who gets transferred **fails**, whatever the score.
- Reading the DQ line to a disqualified consumer **passes**.
- "If this turns out to be a scam, don't call me again" is **not** a do-not-call request.
- A consumer who only swears is **not** a do-not-call request.
- An approved script line is **never** an instant fail — including "the Affordable Care Act is free health
  insurance" and "you may now begin to receive your assistance for 2026", both of which management has
  confirmed stay as written.
- Medicare script wording is not an ACA violation, and vice versa.
- Every objection resolves to official rebuttal wording from `script.md` — none empty.
- Top-agent example lines contain no consumer names and no banned words.
