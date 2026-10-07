# Medicare objection library (the data behind the consumer)

Every objection the simulated consumer can raise, with how often it actually happened on **3,996 analyzed
Medicare fronter calls** and how often the call died right after it. This table is the heart of the realism:
personas draw their objections weighted by these numbers, so practice feels like a real shift instead of a
greatest-hits reel.

- **Seen %** — share of real conversations where this came up.
- **Lost %** — share of those where the consumer hung up, escalated, or demanded DNC right after it.
- **Weight** — draw weight in the simulator: `round(seen*10 + lost*1.5)`. Frequency decides how often an agent
  meets it; the loss rate buys extra reps on the objections that actually cost money.
- **Min level** — the difficulty tier this objection unlocks at.

Two weights are set by the training team rather than the formula: `already_have_it` is raised to 300 (it's the
objection reps face most, usually at the very first Parts A and B question, and training hammers it), and
`what_benefits` is cut to 20 (the call data overcounts it; it rarely comes up as a real objection on the phone).

Note what the data says: `transfer_no` (refusing the transfer) is only the 10th most common objection but kills
**28.3%** of the calls it appears in, and plain `not_interested` kills 24%. The two highest-frequency ones
(`already_have_it`, `what_benefits`) are nearly harmless. That gap is why weight is not just frequency.

|   # | id                        | What the consumer means                              | Seen % | Lost % | Weight | Min level |
| --: | ------------------------- | ---------------------------------------------------- | -----: | -----: | -----: | --------: |
|   1 | `already_have_it`         | I've already got it                                  |   16.3 |    3.5 |    300 |         1 |
|   2 | `busy`                    | I'm busy / call me back                              |    8.3 |   14.9 |    105 |         1 |
|   3 | `dont_want_change`        | I don't want to change / I like my plan              |    8.2 |    4.9 |     89 |         2 |
|   4 | `what_benefits`           | What benefits? What's the card for?                  |    7.8 |    0.6 |     20 |         1 |
|   5 | `who_are_you`             | Who are you with? / Why are you calling me?          |    7.1 |    1.0 |     72 |         1 |
|   6 | `never_received_card`     | I never got any card                                 |    6.9 |    0.0 |     69 |         1 |
|   7 | `doubts_eligibility`      | I don't think I qualify for that                     |    6.6 |    4.2 |     72 |         1 |
|   8 | `has_different_card`      | I don't have that card / I have a different card     |    6.4 |    0.4 |     65 |         1 |
|   9 | `just_did_dont_qualify`   | I just did this — they said I don't qualify          |    4.9 |    3.3 |     54 |         3 |
|  10 | `transfer_no`             | Refuses the transfer / won't wait for the specialist |    4.3 |   28.3 |     85 |         2 |
|  11 | `not_interested`          | I'm not interested                                   |    3.7 |   24.0 |     73 |         1 |
|  12 | `just_did_best`           | I just did this — they said I have the best          |    3.0 |    8.3 |     42 |         2 |
|  13 | `too_many_calls`          | This is the 16th call today                          |    2.6 |    6.7 |     36 |         3 |
|  14 | `personal_info`           | I'm not giving out personal information              |    2.5 |    5.6 |     33 |         3 |
|  15 | `never_get_anything`      | Y'all call all the time and I never get anything     |    2.1 |    2.3 |     24 |         2 |
|  16 | `scam`                    | This is a scam                                       |    1.6 |    7.7 |     28 |         2 |
|  17 | `how_much`                | How much do I qualify for? (can't answer)            |    1.5 |    3.1 |     20 |         2 |
|  18 | `is_this_insurance`       | Is this insurance? Part C?                           |    1.4 |    0.0 |     14 |         3 |
|  19 | `afraid_to_lose_coverage` | I'm not getting scammed out of my coverage           |    1.0 |    0.0 |     10 |         3 |
|  20 | `trying_to_sell_me`       | You're just trying to sell me something              |    0.9 |   11.1 |     26 |         3 |
|  21 | `how_get_info`            | How did you get my number?                           |    0.7 |    6.7 |     17 |         2 |
|  22 | `will_i_get_more`         | So they're going to give me more?                    |    0.6 |    0.0 |      6 |         2 |
|  23 | `already_enrolled`        | I already did my insurance for next year             |    0.6 |   16.7 |     31 |         2 |
|  24 | `caller_id_scam`          | My phone said 'potential scam'                       |    0.4 |    6.7 |     14 |         2 |

---

## Per objection: what they say, what we say back

For each one: real consumer phrasings (escalating with level), the **official rebuttal** (pulled live from the
script file — never hardcoded), and what the two model agents say in their own words.

### `already_have_it` — I've already got it

_Seen on 16.3% of real calls · loses 3.5% of them · draw weight 300 · unlocks at level 1_

**Consumer says (level 1 → 5):**

- “I already have that card.”
- “I already get all that stuff.”
- “I get my OTC card with Humana.”
- “I already signed up with Aetna for 2026.”
- “I get the U Card with United.”
- “I get the Flex Card with Humana.”

**Official rebuttal (script):** Oh no, I understand. We were just calling about the increases. There have been some NEW allowances and NEW increases that have JUST become available.

**Model agents, live:**

- _Pace Model:_ “They're gonna see if there may be an increase here for you. Do you have Medicaid as well?”
- _Compliance Model:_ “I understand, that's why I'm calling. There's been some new allowances. You do still have Medicare parts A and B, correct?”

### `busy` — I'm busy / call me back

_Seen on 8.3% of real calls · loses 14.9% of them · draw weight 105 · unlocks at level 1_

**Consumer says (level 1 → 5):**

- “I'm busy right now, call me back later.”
- “I'm at the doctor, I can't talk.”
- “I'm literally driving right now, man.”

**Official rebuttal (script):** Oh, let me assure you this will be SUPER brief. I just have 3 SUPER quick questions and I'll bring the agent on.

**Model agents, live:**

- _Pace Model:_ “Yes, ma'am. I'm gonna get you in as fast as I can. Hold on.”
- _Compliance Model:_ “Let me assure you, this will be super brief. Do you have the red, white and blue card by chance?”

### `dont_want_change` — I don't want to change / I like my plan

_Seen on 8.2% of real calls · loses 4.9% of them · draw weight 89 · unlocks at level 2_

**Consumer says (level 1 → 5):**

- “I like my plan, I'm not changing anything.”
- “Do I have to change my insurance?”
- “I've had the same plan for ten years and I'm happy with it.”

**Official rebuttal (script):** Oh (Customer's Name), changing your insurance is ONLY something you can do. I don't even have the power to do that. There have just been some new allowances and new benefits that have JUST become available. We're just here to facilitate your free review.

**Model agents, live:**

- _Pace Model:_ “Nobody can do that but you, hon. This is just about the additional food benefits.”
- _Compliance Model:_ “No, I'm only calling about the additional allowances. And that is the red, white and blue card, correct?”

### `what_benefits` — What benefits? What's the card for?

_Seen on 7.8% of real calls · loses 0.6% of them · draw weight 20 · unlocks at level 1_

**Consumer says (level 1 → 5):**

- “What card? What's it for?”
- “What kind of benefits are we talking about?”

**Official rebuttal (script):** So the healthy food card is there to help you with groceries on a monthly basis, and the Flex Card covers expenses such as dental, hearing, vision and utilities. There's also a Part B give back for Medicare, which helps cover some, if not ALL, of your Part B premium.

**Model agents, live:**

- _Pace Model:_ “It's about additional food and grocery benefits. They'll do your review and let you know what may be available.”
- _Compliance Model:_ “It's a food and utility card for members with Medicare parts A and B. I still have you out in [state], correct?”

### `who_are_you` — Who are you with? / Why are you calling me?

_Seen on 7.1% of real calls · loses 1.0% of them · draw weight 72 · unlocks at level 1_

**Consumer says (level 1 → 5):**

- “Who is this? Who are you with?”
- “Why are you calling me?”
- “What company did you say?”
- “Why the f\*\*\* are you calling me?”
- “Who the hell is this and why do you keep calling?”

**Official rebuttal (script):** Oh, I'm with America's Health. We work with over 25 different health insurance providers. We're just here to facilitate your free review.

**Model agents, live:**

- _Pace Model:_ “This is [your name] with America's Health on a recorded line, about your utilities and groceries card. You still have Medicare A and B, correct?”
- _Compliance Model:_ “America's Health, on a recorded line. You do still have Medicare parts A and B, correct?”

### `never_received_card` — I never got any card

_Seen on 6.9% of real calls · loses 0.0% of them · draw weight 69 · unlocks at level 1_

**Consumer says (level 1 → 5):**

- “I never got no card.”
- “What card? I never received anything from Medicare.”
- “Y'all keep saying card, I ain't never got a damn card.”

**Official rebuttal (script):** Oh no, I understand. We were just calling about the increases. There have been some NEW allowances and NEW increases that have JUST become available.

**Model agents, live:**

- _Pace Model:_ “No worries. You have Medicaid with your Medicare?”
- _Compliance Model:_ “Right, I know. That's why I'm calling you. You do still have Medicare parts A and B, correct?”

### `doubts_eligibility` — I don't think I qualify for that

_Seen on 6.6% of real calls · loses 4.2% of them · draw weight 72 · unlocks at level 1_

**Consumer says (level 1 → 5):**

- “I don't think I qualify for anything like that.”
- “I make too much, they never give me nothing.”

**Official rebuttal (script):** I understand, but MANY people are finding out RIGHT now that they ACTUALLY qualify for WAY more than they're receiving.

**Model agents, live:**

- _Pace Model:_ “That's exactly why they're checking for you today. You have your red, white and blue card, correct?”
- _Compliance Model:_ “That's why I'm calling. You may qualify. You do have Medicare parts A and B, correct?”

### `has_different_card` — I don't have that card / I have a different card

_Seen on 6.4% of real calls · loses 0.4% of them · draw weight 65 · unlocks at level 1_

**Consumer says (level 1 → 5):**

- “I don't have a red, white and blue card. I have my Humana card.”
- “The card I got is blue and white, it's from my plan.”

**Official rebuttal (script):** Okay, no problem, but you DO have your Medicare number, correct?

**Model agents, live:**

- _Pace Model:_ “Yes, ma'am, you're good. Do you have Medicaid as well?”
- _Compliance Model:_ “Okay, no problem. And do you receive Medicaid along with your Medicare plan?”

### `just_did_dont_qualify` — I just did this — they said I don't qualify

_Seen on 4.9% of real calls · loses 3.3% of them · draw weight 54 · unlocks at level 3_

**Consumer says (level 1 → 5):**

- “I already did this and they told me I don't qualify for nothing.”

**Official rebuttal (script):** Oh, you probably spoke with one of our small competitors. The difference is WE work with over 25 different health insurance providers, so people ARE finding that with US, they qualify for WAY more benefits than they're receiving.

**Model agents, live:**

- _Pace Model:_ “Benefits may be available in your area now, that's why you're getting a new review today. Okay?”
- _Compliance Model:_ “That's why I'm calling you. There's been some new allowances that just became available. You still have Medicare parts A and B, correct?”

### `transfer_no` — Refuses the transfer / won't wait for the specialist

_Seen on 4.3% of real calls · loses 28.3% of them · draw weight 85 · unlocks at level 2_

**Consumer says (level 1 → 5):**

- “No, I don't have a few minutes.”
- “I'm not waiting on hold for somebody.”

**Official rebuttal (script):** Well, I understand, but you've worked your whole life for these Medicare benefits. Give the agent JUST a few minutes to review and compare your plan to make SURE you're not missing out on ANYTHING you may be entitled to.

**Model agents, live:**

- _Pace Model:_ “I understand. We let it ring about a minute, so keep holding for me.”
- _Compliance Model:_ “I'll stay on the line with you until they pick up. Okay?”

### `not_interested` — I'm not interested

_Seen on 3.7% of real calls · loses 24.0% of them · draw weight 73 · unlocks at level 1_

**Consumer says (level 1 → 5):**

- “I'm not interested.”
- “Nah, not interested, thanks.”
- “I said I'm not interested, what part of that don't you get?”

**Official rebuttal (script):** I understand, but MANY people are finding out RIGHT now that they ACTUALLY qualify for WAY more than they're receiving.

**Model agents, live:**

- _Pace Model:_ “It's about your food and groceries benefit. I'm with America's Health on a recorded line. You still have Medicare A and B, correct?”
- _Compliance Model:_ “That card is exclusive for members with Medicare parts A and B. You do still have Medicare parts A and B?”

### `just_did_best` — I just did this — they said I have the best

_Seen on 3.0% of real calls · loses 8.3% of them · draw weight 42 · unlocks at level 2_

**Consumer says (level 1 → 5):**

- “Somebody just called me last week and said I already have the best plan.”

**Official rebuttal (script):** Oh, you probably spoke with one of our small competitors. But I DO see here that you may be ENTITLED to some additional food benefits since the food prices HAVE gone up.

**Model agents, live:**

- _Pace Model:_ “How long ago was your review? There may be an increase now, so let's check.”
- _Compliance Model:_ “I understand. That's why I'm calling you. You still have Medicare parts A and B, correct?”

### `too_many_calls` — This is the 16th call today

_Seen on 2.6% of real calls · loses 6.7% of them · draw weight 36 · unlocks at level 3_

**Consumer says (level 1 → 5):**

- “This is the 16th telemarketing call I've had today and I am so sick of it.”
- “Y'all been calling me all damn day, I'm tired of it.”

**Official rebuttal (script):** Oh, let me assure you this will be SUPER brief. I just have 3 SUPER quick questions and I'll bring the agent on.

**Model agents, live:**

- _Pace Model:_ “I hear you, a lot of companies are calling. Do you still have your red, white and blue card?”
- _Compliance Model:_ “I understand. I understand. This will be super brief.”

### `personal_info` — I'm not giving out personal information

_Seen on 2.5% of real calls · loses 5.6% of them · draw weight 33 · unlocks at level 3_

**Consumer says (level 1 → 5):**

- “I don't give my information out over the phone.”
- “I'm not giving you my Medicare number.”
- “Do I have to give personal information?”

**Official rebuttal (script):** Oh, let me assure you (customer's name), I'm not asking for any credit card or banking information. I'm with America's Health. We work with over 25 different health insurance providers. We're just here to facilitate your free review.

**Model agents, live:**

- _Pace Model:_ “No, ma'am, I just verified the basics. I'm getting you in so they can go over your groceries benefit.”
- _Compliance Model:_ “I'm not asking for that. Let me just verify, that is the red, white and blue card, correct?”

### `never_get_anything` — Y'all call all the time and I never get anything

_Seen on 2.1% of real calls · loses 2.3% of them · draw weight 24 · unlocks at level 2_

**Consumer says (level 1 → 5):**

- “They call me all the time about this and I don't never receive nothing.”
- “Every week somebody calls about a card and I ain't never got one.”

**Official rebuttal (script):** Oh no, I understand. We were just calling about the increases. There have been some NEW allowances and NEW increases that have JUST become available.

**Model agents, live:**

- _Pace Model:_ “Here we go, they're on the line now for you. Hold on.”
- _Compliance Model:_ “In the meantime, how's the weather out there today?”

### `scam` — This is a scam

_Seen on 1.6% of real calls · loses 7.7% of them · draw weight 28 · unlocks at level 2_

**Consumer says (level 1 → 5):**

- “This sounds like a scam.”
- “How do I know you're not a scammer?”
- “You people are all scammers, I see it on the news every day.”

**Official rebuttal (script):** Oh, let me assure you (customer's name), I'm not asking for any credit card or banking information. I'm with America's Health. We work with over 25 different health insurance providers. We're just here to facilitate your free review.

**Model agents, live:**

- _Pace Model:_ “No, ma'am. We connect you to a live Medicare specialist.”
- _Compliance Model:_ “That's a great question. That's why I was calling you. You do still have Medicare parts A and B, correct?”

### `how_much` — How much do I qualify for? (can't answer)

_Seen on 1.5% of real calls · loses 3.1% of them · draw weight 20 · unlocks at level 2_

**Consumer says (level 1 → 5):**

- “How much money is on the card?”
- “How much would I get exactly?”

**Official rebuttal (script):** That would be a great question for the agent once I get you there.

**Model agents, live:**

- _Pace Model:_ “Let me get you in, hon. Do you have any insurance through work or the VA?”
- _Compliance Model:_ “That would be a great question for the agent once I get you there.”

### `is_this_insurance` — Is this insurance? Part C?

_Seen on 1.4% of real calls · loses 0.0% of them · draw weight 14 · unlocks at level 3_

**Consumer says (level 1 → 5):**

- “Is this insurance? The Part C insurance?”
- “Is this one of those Medicare Advantage things they keep pushing?”

**Official rebuttal (script):** That would be a great question for the agent once I get you there.

### `afraid_to_lose_coverage` — I'm not getting scammed out of my coverage

_Seen on 1.0% of real calls · loses 0.0% of them · draw weight 10 · unlocks at level 3_

**Consumer says (level 1 → 5):**

- “I'm not going to do anything that gets me scammed out of the coverage I already have.”
- “If I have to change my insurance plan to get whatever you're selling, I don't want it.”

**Official rebuttal (script):** Oh (Customer's Name), changing your insurance is ONLY something you can do. I don't even have the power to do that. There have just been some new allowances and new benefits that have JUST become available. We're just here to facilitate your free review.

**Model agents, live:**

- _Pace Model:_ “No, ma'am. No one can change who you're with. You'd just get a live review with a Medicare specialist.”
- _Compliance Model:_ “Changing your insurance is only something you can do. You do still have Medicare parts A and B, correct?”

### `trying_to_sell_me` — You're just trying to sell me something

_Seen on 0.9% of real calls · loses 11.1% of them · draw weight 26 · unlocks at level 3_

**Consumer says (level 1 → 5):**

- “You're just trying to sell me something.”
- “What are you selling? Just tell me what you're selling.”

**Official rebuttal (script):** Oh (Customer's Name), changing your insurance is ONLY something you can do. I don't even have the power to do that. There have just been some new allowances and new benefits that have JUST become available. We're just here to facilitate your free review.

**Model agents, live:**

- _Pace Model:_ “I hear you. No one can change your plan but you.”
- _Compliance Model:_ “I'm just a qualifier. I call to see if you may qualify for the new food and utility benefits.”

### `how_get_info` — How did you get my number?

_Seen on 0.7% of real calls · loses 6.7% of them · draw weight 17 · unlocks at level 2_

**Consumer says (level 1 → 5):**

- “How did you get my number?”
- “Where'd you get my information?”
- “Who gave you my phone number? I never signed up for anything.”
- “How the f\*\*\* did you get my number?”
- “Where the hell did you get my number, huh?”

**Official rebuttal (script):** Oh, so either you or a family member went online and expressed interest in a free review. We're just here to facilitate that for you.

### `will_i_get_more` — So they're going to give me more?

_Seen on 0.6% of real calls · loses 0.0% of them · draw weight 6 · unlocks at level 2_

**Consumer says (level 1 → 5):**

- “Are they going to give me more?”
- “So how much more money am I getting on the card?”

**Official rebuttal (script):** That would be a great question for the agent once I get you there.

### `already_enrolled` — I already did my insurance for next year

_Seen on 0.6% of real calls · loses 16.7% of them · draw weight 31 · unlocks at level 2_

**Consumer says (level 1 → 5):**

- “I already did my insurance for next year. I got Humana.”
- “My plan's all set for next year, I just did all that.”

**Official rebuttal (script):** Oh no, I understand. We were just calling about the increases. There have been some NEW allowances and NEW increases that have JUST become available.

### `caller_id_scam` — My phone said 'potential scam'

_Seen on 0.4% of real calls · loses 6.7% of them · draw weight 14 · unlocks at level 2_

**Consumer says (level 1 → 5):**

- “Is this a scam? Because it came up as potential scam on my phone.”
- “I've seen this number come up all week and it says potential scam, so I kept declining it.”
- “My phone says Scam Likely. Why the hell should I trust you?”

**Official rebuttal (script):** Oh, let me assure you (customer's name), I'm not asking for any credit card or banking information. I'm with America's Health. We work with over 25 different health insurance providers. We're just here to facilitate your free review.

**Model agents, live:**

- _Pace Model:_ “That's your phone company. It says potential because they don't recognize who's calling.”
- _Compliance Model:_ “I understand. I'm with America's Health on a recorded line. You do still have Medicare parts A and B, correct?”

---

## Difficulty levels

| Level | Name      | Objections drawn | Temperament                                                                                                                             |
| ----: | --------- | :--------------: | --------------------------------------------------------------------------------------------------------------------------------------- |
|     1 | Warm Up   |       0–1        | polite, a little distracted, cooperative once they understand                                                                           |
|     2 | Guarded   |       1–2        | guarded, impatient, wants to get off the phone                                                                                          |
|     3 | Skeptical |       2–3        | skeptical, sharp, asks pointed questions, won't give info easily                                                                        |
|     4 | Hostile   |       3–4        | rude and hostile, interrupts, curses at the agent, sarcastic                                                                            |
|     5 | Nightmare |       3–5        | unhinged: yells, swears constantly, rambles off-topic, accuses the agent, may be confused or hard of hearing, may try to trap the agent |

Starting patience is `10 - level`, so a level 5 consumer is five bad turns from hanging up.

---

## Disqualifier traps

Drawn when a persona's correct outcome is DQ. The consumer answers truthfully **only when asked** (or
volunteers it, which also counts) — the agent has to catch it and read the DQ line instead of transferring.

| id              | Hidden truth                                                        |
| --------------- | ------------------------------------------------------------------- |
| `va`            | Has VA health care (military veteran).                              |
| `tricare`       | Has Tricare for Life through a spouse's military service.           |
| `employer`      | Still has insurance through their employer.                         |
| `retiree`       | Has retiree health insurance from a former job (city pension plan). |
| `no_part_b`     | Only has Part A, never signed up for Part B.                        |
| `medicaid_only` | Only has Medicaid. No Medicare Parts A & B at all.                  |

---

## Quirks

One is attached to every persona at level 2+. Half of these came straight off real recordings, and they do
more for realism than any amount of prompt tuning:

- TV blaring in the background
- hard of hearing, asks you to repeat things
- keeps mentioning their grandson who 'handles this stuff'
- eating while talking
- was just woken up from a nap
- talks about their bad knee
- dog barking the whole call
- just got off the phone with another telemarketer
- on a cheap phone that cuts out
- calls the agent 'baby' or 'hon'
- just woke up and is groggy
- hard of hearing and keeps asking 'who are you?'
- wants to tell you about their health problems
- has had 15 sales calls today
- at work and distracted
- very religious, says 'amen' a lot
- thinks every caller is trying to switch their insurance

---

## Outcome mix by level

| Level | Transfer |  DQ | DNC |
| ----: | -------: | --: | --: |
|   1–2 |     100% |   — |   — |
|   3–4 |      82% | 18% |   — |
|     5 |      61% | 14% | 25% |

Levels 1 and 2 are always transferable so a new hire can learn the flow. DQs and DNC demands only appear once
they can handle the script, and at level 5 roughly 2 in 5 calls are _not_ supposed to end in a transfer — which
is the point: the wrong outcome cannot pass, so they have to learn when **not** to transfer.

(Measured over 4,000 generated personas per level, so these are the real draw rates, not the intent.)
