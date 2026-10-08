# Pitch University — ACA vertical

**From:** Nate, Jade Global · **6 October 2026**

The ACA vertical, ready to set up on your side. Same engine as Medicare, different data pack — but the
qualifying logic runs in the opposite direction, so start with the setup doc rather than assuming it
behaves like Medicare.

## Start here

| Read | For |
|---|---|
| **[docs/01_SETUP_ACA.md](docs/01_SETUP_ACA.md)** | What to swap in, and the ten things that catch people out |
| **[docs/02_ACA_RULES.md](docs/02_ACA_RULES.md)** | Every rule in one place: steps, disqualifiers, instant fails, DNC, dispositions, scoring |
| **[docs/03_VOICE_CASTING_ACA.md](docs/03_VOICE_CASTING_ACA.md)** | Voices for a 19–64 demographic, and the fix for the gender mismatch |
| **[docs/04_PROMPTS_ACA.md](docs/04_PROMPTS_ACA.md)** | The consumer brief and grader rules, verbatim |

## What's in the box

```
content/
  script.md                        the approved script - source of truth for wording
  course.json                      8 modules, 41 lessons, 88 quiz questions, 34 exam questions
knowledge_base/
  OBJECTIONS_AND_PERSONAS.md       22 objections with real frequencies, the nine DQs, persona fields
  aca_objections.json              the same library, machine-readable
  findings/                        the call analysis the numbers came from
docs/                              the four documents above
```

## The one-paragraph version

ACA qualifies people **without** coverage. Medicare, Medicaid, work insurance, VA, private, state, SSDI
and non-citizens are all disqualifiers — nine in total — and transferring one is the worst outcome on the
campaign. A Marketplace plan is *not* a disqualifier. The agent confirms the state, confirms no coverage,
confirms age 19+, confirms no coverage **again as a separate ask**, gets a spoken okay, and completes a
warm handoff naming the state. The two most-skipped steps on real calls are the second confirmation
(33.8%) and the verbal consent (18.9%), so those are the two your simulator should be hardest on.

## Where the numbers come from

3,979 analysed ACA calls from Sept–Oct 2026, plus 1,326 calls from the five top-performing agents and 57
calls your QA team flagged by hand. Objection frequencies, the phrasings consumers actually use, and the
top-agent example lines are all drawn from that set rather than invented.

Because that export was **transfers only**, there is no "which objection loses the call" figure for ACA.
Draw weights use how often agents handle an objection *badly* instead, which is documented in the
knowledge base.

## Two things to keep as they are

1. **No raw transcripts or recordings are included, and none will be sent.** These are real consumers.
   Everything here is derived and scrubbed — names, phones, Social Security numbers, member IDs and ZIP
   codes replaced with placeholders.
2. **Top agents stay unnamed.** Their lines are training material; their identities are not.

## Rules that are management decisions, not implementation choices

- "ACA" and "the Affordable Care Act" are the same thing — never mark an agent down for either.
- "The Affordable Care Act is free health insurance" and "you may now begin to receive your assistance for
  2026" are approved script lines and must never be flagged as violations.
- Under 19 is a **DNC**, not a DQ.
- Swearing alone is an objection, not a do-not-call request. *(Note: this is Jade Global's rule for ACA.
  Your QA manager has set a broader DNC rule for the Medicare build you host. The difference is
  deliberate — see the v2 build notes.)*

Questions: nate@jadeglobal.io
