# Voice casting for ACA

Short document, one real change from Medicare, plus the fix for the gender mismatch your QA manager found.

---

## The demographic is twenty years younger

Medicare consumers are 65–88. **ACA consumers are 19–64.** Our Medicare voices are deliberately slowed and
pitched down for older speakers:

| Age | Speaking rate | Pitch |
|---|--:|--:|
| under 73 | 0.95 | −1.0 |
| 73–80 | 0.92 | −2.0 |
| 81+ | 0.88 | −3.0 |

Applied to a 34-year-old consumer that sounds wrong immediately. For ACA, use natural rate and pitch across
the range, with only a slight slowing at the top end:

| Age | Speaking rate | Pitch |
|---|--:|--:|
| 19–39 | 1.02 | 0.0 |
| 40–54 | 1.00 | −0.5 |
| 55–64 | 0.96 | −1.0 |
| 65+ (Medicare disqualifier personas) | 0.92 | −2.0 |

Note the last row: ACA personas carrying the **Medicare** disqualifier are 66–78 by design, because age is
a genuine tell the agent should learn to hear. Those should sound older.

## Fixing the gender mismatch

> *"The gender of the customer was fickle. I had guy names that would have a female AI voice, vice versa."*

Bind the voice to the persona at **creation**, not per utterance, and derive everything from the persona's
seed:

```
1. pick gender
2. pick the first name from the list for that gender
3. pick the voice from the pool for that gender
4. derive rate and pitch from age
```

Same seed, same consumer, same voice — including after a page reload mid-call. If the voice is chosen at
speak time, a reload or a retry re-rolls it and the consumer changes sex mid-conversation.

## Voice pools

Keep separate male and female pools and mix price tiers rather than defaulting to the premium ones —
studio-grade voices cost roughly **10× per character**, which matters once agents are doing voice reps
daily. One premium voice per pool, two standard, is the mix we settled on.

Two gotchas worth knowing before you debug them:

- **Some premium voices reject the pitch parameter outright** and fail the whole synthesis request. Keep a
  list of voice names that only receive `speakingRate`.
- **Opus requires an explicit sample rate.** Browsers record at 48000 Hz; leaving it unset produces
  "Opus sample rate (0)" errors that look like audio problems rather than config problems.

## Recognition settings

Phrase-boost the script's own vocabulary. For ACA that list is:

> Affordable Care Act · ACA · Marketplace · Obamacare · subsidy · allowance · Medicare · Medicaid ·
> work insurance · America's Health · recorded line · coordinator · licensed agent · state · do not call

Without boosts, "Parts A and B" came back as "parts anb" on the Medicare build and the grader marked a
correct step as missed. The ACA equivalents are "M/M/WI", "Medicaid" heard as "Medicare", and the company
name — which the transcription fumbles often enough that we had to caveat a whole finding because of it.

## Pacing, from real calls

Measured on 5,130 recorded calls (Medicare, but the shape holds for any fronter campaign):

| | Median |
|---|--:|
| Turns per call | 26 |
| Agent words per turn | 16 |
| **Consumer words per turn** | **2** |
| Agent seconds per turn | 5.2s |
| **Consumer seconds per turn** | **0.9s** |

**62.5% of consumer turns are three words or fewer** — "yes", "okay", "no", "yeah", "hello?", "correct".

Cap most simulated consumer replies at a handful of words and save the long turns for objections and
tangents. A voice bot that answers in fluent sentences reads as wrong before voice quality matters at all.
