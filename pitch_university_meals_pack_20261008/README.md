# Pitch University — Meals vertical

**From:** Nate, Jade Global · **8 October 2026**

The Medicaid meals vertical, ready to set up on your side. Third pack after Medicare and ACA, same shape.

## Start here

| Read | For |
|---|---|
| **[docs/01_SETUP_MEALS.md](docs/01_SETUP_MEALS.md)** | What to build, in order, and the ten things that catch people out |
| **[docs/02_MEALS_RULES.md](docs/02_MEALS_RULES.md)** | Every rule in one place |
| **[docs/04_PROMPTS_MEALS.md](docs/04_PROMPTS_MEALS.md)** | The consumer brief and grader rules, verbatim |

(There is no separate voice document for this vertical — the voice guidance in the ACA pack applies
unchanged, except that these consumers are NYC Medicaid recipients aged roughly 30–85, so cast across that
range rather than skewing old.)

## What's in the box

```
content/
  script.md                      the approved script - source of truth for wording
  course.json                    8 modules, 38 lessons, 70 quiz questions, 32 exam questions
knowledge_base/
  OBJECTIONS_AND_PERSONAS.md     12 objections with what each one COSTS, the Medicaid branch, borough traps
  meals_objections.json          the same library, machine-readable
  findings/                      the call analysis behind every number here
docs/                            the three documents above
```

## The one-paragraph version

A meal benefit already included in the consumer's Medicaid — up to 3 meals a day, no cost, no change to
coverage. The agent confirms Medicaid, borough, ZIP, then all of it again, gets permission, and warm-
transfers to a screener. Calls run about 90 seconds. **Never asking the Medicaid question is treated as
fraud and fails the call outright.** Qualifying is NYC-only: five boroughs, ten named Medicaid plans, and a
copay fallback question for the one consumer in five who genuinely does not know what coverage they have.

## What makes this pack different from the last two

**We could measure what objections cost.** The Medicare and ACA exports were transfers only. This sample
had **2,067 lost calls** next to 1,196 transfers, so the objection weights are driven by real losses rather
than by how often agents fumbled something:

- "Not interested" appears on 14.9% of calls and **97.5% of those calls never transfer** — and on more than
  half of them, no rebuttal was attempted at all.
- The most common objection, "what is this?", is the **safest** at 51.9%.
- Median brush-off call with no rebuttal: **27 seconds**. With one: **43**.

Build the consumer model to hang up fast, and grade the pause as well as the words.

## Where the numbers come from

3,263 analysed calls from Sept–Oct 2026 (2,200 Not Interested, 1,200 Transfer, sampled across the named
onshore floor), plus 969 transferred calls from the four top-performing agents, whose verbatim lines appear
in the objection library.

## Two things to keep as they are

1. **No raw transcripts or recordings.** Everything here is derived and scrubbed — names, phones, Social
   Security numbers and ZIPs replaced with placeholders.
2. **Top agents stay unnamed.** Their lines are training material; their identities are not.

## Management decisions, not implementation choices

- **"You're already approved" / "preapproved" is approved script wording.** Never flag it.
- **Never asking about Medicaid is fraud** — automatic fail, no exceptions.
- Swearing alone is an objection, not a do-not-call request.
- Two gaps exist in the script itself and are flagged in the setup doc: no approved rebuttal for "I was
  told I don't qualify", and no written do-not-call disclaimer.

Questions: nate@jadeglobal.io
