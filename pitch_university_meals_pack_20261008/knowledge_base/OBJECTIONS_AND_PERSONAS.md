# Meals objection library and personas

Drawn from **3,263 analysed Medicaid meals calls** — 1,196 that transferred and 2,067 that did not.

This is the first vertical where the sample contains losses, so the weight is driven by what an objection
**costs**, not just how often it appears:

```
weight = seen_pct * 10 + ended_badly_pct * 1.5
```

where `ended_badly_pct` is how often the consumer hung up, refused firmly or demanded DNC immediately after it.

| # | id | What they mean | Seen % | Calls lost % | Ended badly % | Weight | Min level |
|--:|---|---|--:|--:|--:|--:|--:|
| 1 | `not_interested` | I'm not interested | 14.9 | 97.5 | 88.2 | 281 | 1 |
| 2 | `what_is_this` | What is this? Why are you calling? | 19.8 | 51.9 | 18.2 | 225 | 1 |
| 3 | `busy_callback` | I'm busy / call me back | 10.6 | 82.6 | 73.6 | 216 | 1 |
| 4 | `dont_need_it` | I don't need it / give it to someone else | 8.5 | 89.6 | 79.5 | 204 | 2 |
| 5 | `who_are_you` | Who are you with? | 13.5 | 68.9 | 24.8 | 172 | 1 |
| 6 | `already_have_it` | I already get meals | 4.0 | 63.1 | 36.2 | 94 | 2 |
| 7 | `told_dont_qualify` | I was told I don't qualify | 1.7 | 66.1 | 42.9 | 81 | 3 |
| 8 | `personal_info` | I'm not giving you my information | 1.7 | 52.6 | 42.1 | 80 | 3 |
| 9 | `already_signed_up` | I already signed up with someone | 1.7 | 62.5 | 41.1 | 79 | 2 |
| 10 | `dont_like_meals` | I don't like the meals | 1.7 | 58.2 | 40.0 | 77 | 2 |
| 11 | `scam` | This sounds like a scam | 1.1 | 52.8 | 36.1 | 65 | 3 |
| 12 | `how_much_cost` | How much is this going to cost me? | 0.7 | 30.4 | 8.7 | 20 | 1 |

Read the top three rows together: **the most common objection is the safest**. "What is this?" appears on
one call in five and agents handle it well. The brush-offs are less frequent and far more expensive — and on
more than half of the "not interested" calls, **no rebuttal was attempted at all**.

One more number that should shape your consumer model: when no rebuttal came, the median brush-off call ran
**27 seconds**. When one did, 43. These are not conversations — they are one sentence before the line dies.

---

## Per objection

### `not_interested` — I'm not interested
*Seen on 14.9% of calls · 97.5% of those calls were lost · weight 281 · unlocks at level 1*

**Consumer says:**
- “No, thank you.”
- “I'm not interested.”
- “I'm not interested, thank you.”
- “No, I'm not interested.”

**Official rebuttal (from script.md → “Not interested”):** I understand, but groceries are NOT getting any cheaper. You are ALREADY approved for up to three meals at NO cost and this is INCLUDED in your Medicaid. I just need to confirm... (GO RIGHT BACK WHERE YOU WERE, DON'T STOP OR PAUSE)

**Top agents, verbatim:**
- “I understand, but groceries are not getting any cheaper. You are already approved for up to three meals at no cost. And this is included in your Medicaid. I just need to confirm if you're still receiving Medicaid.”

### `what_is_this` — What is this? Why are you calling?
*Seen on 19.8% of calls · 51.9% of those calls were lost · weight 225 · unlocks at level 1*

**Consumer says:**
- “What?”
- “What is this about?”
- “What is this for?”
- “What is this?”

**Official rebuttal (from script.md → “What is this / Why are you calling me?”):** This is a meal delivery benefit included in your Medicaid. It provides up to 3 meals daily at no cost, and no change to coverage. I just need to confirm... (GO RIGHT BACK WHERE YOU WERE, DON'T STOP OR PAUSE)

**Top agents, verbatim:**
- “This is a meal delivery benefit included in your Medicaid. It provides up to three meals daily at no cost and no change to coverage.”
- “This is a meal delivery benefit. Included in your Medicaid, it provides up to three meals daily at no cost and no change to coverage.”

### `busy_callback` — I'm busy / call me back
*Seen on 10.6% of calls · 82.6% of those calls were lost · weight 216 · unlocks at level 1*

**Consumer says:**
- “Would I be able to call back just because I'm not with.”
- “Yeah, but I'm not out in the street. I'm not in my house. Can you call me?”
- “No, I don't understand what you're saying, Papi. I'm busy at the moment.”
- “Can you call me back? I'm at work right now.”

**Official rebuttal (from script.md → “I'm busy / Call me back”):** Oh let me assure you this will be SUPER brief, I just have 3 SUPER quick questions and I'll bring the screener on. I just need to confirm... (GO RIGHT BACK WHERE YOU WERE, DON'T STOP OR PAUSE)

**Top agents, verbatim:**
- “Let me assure this will be super brief. I just have three super quick questions and I'll bring the screener on.”
- “Yes, sir. I will really make it quick for you.”

### `dont_need_it` — I don't need it / give it to someone else
*Seen on 8.5% of calls · 89.6% of those calls were lost · weight 204 · unlocks at level 2*

**Consumer says:**
- “I don't need it.”
- “I don't need that.”
- “No, I don't need it.”
- “I don't get meal deliveries.”

**Official rebuttal (from script.md → “I don't need it”):** I understand, but with groceries and the cost of living going up, having three meals delivered at NO cost can take a little pressure off your grocery budget. And if you don't personally need them, you could ALWAYS give them to someone in your family or someone who could use the help. You're already approved for it, so there's nothing extra you're paying for. I just need to confirm... (GO RIGHT BACK WHERE YOU WERE, DON'T STOP OR PAUSE)

**Top agents, verbatim:**
- “Okay, I get that. Just give them a few moments to go over it with you. Make sure you're not missing out on anything you might be interested in. All right?”
- “Oh, even the vegetables and the fruits?”

### `who_are_you` — Who are you with?
*Seen on 13.5% of calls · 68.9% of those calls were lost · weight 172 · unlocks at level 1*

**Consumer says:**
- “Who is this?”
- “Who's calling?”
- “Who's this?”
- “Who's speaking?”

**Official rebuttal (from script.md → “Who are you with?”):** Oh I'm with America's Health. We work with over 25 different health insurance providers. We're just here to facilitate your free review. I just need to confirm... (GO RIGHT BACK WHERE YOU WERE, DON'T STOP OR PAUSE)

**Top agents, verbatim:**
- “Hey, this is [agent]. I'm with America's Health on a recorded line. I was just giving you a quick call today about the Medicaid meal service for you and your family.”
- “Hey, this is Seven. I'm with America's Health on a recorded line. I was just giving you a quick call today about the Medicaid meal service for you and your family.”

### `already_have_it` — I already get meals
*Seen on 4.0% of calls · 63.1% of those calls were lost · weight 94 · unlocks at level 2*

**Consumer says:**
- “This is in reference to Medicaid meals. I already get.”
- “Yeah, we're receiving from God's love.”
- “Is this about the full box? Because I get full boxes.”
- “I say I already have a plan.”

**Official rebuttal (from script.md → “I already have it”):** Yes of course, we were just calling about the EXTRA support, this isn't a replacement. There's actually been some NEW varieties that JUST released so MORE food coming in, we just need to activate it. I just need to confirm... (GO RIGHT BACK WHERE YOU WERE, DON'T STOP OR PAUSE)

**Top agents, verbatim:**
- “Right. We were calling about the meal delivery benefit. Included in your Medicaid, it provides up to three meals daily at no cost and no change to coverage.”
- “Oh, you're already receiving it. And are you receiving everything that you want or are there any other things you haven't received yet that you would like to get?”

### `told_dont_qualify` — I was told I don't qualify
*Seen on 1.7% of calls · 66.1% of those calls were lost · weight 81 · unlocks at level 3*

**Consumer says:**
- “I do not have Medicare. I do not have Medicaid.”
- “Oh, I don't have Medicaid. The delivery would be fine, but I'm not at that age as yet.”
- “I'm okay on the mail service... They always deny me.”
- “I don't have Medicaid. I'm trying to get it back now, but I haven't received it so far.”

**Official rebuttal (from script.md → “What is this / Why are you calling me?”):** This is a meal delivery benefit included in your Medicaid. It provides up to 3 meals daily at no cost, and no change to coverage. I just need to confirm... (GO RIGHT BACK WHERE YOU WERE, DON'T STOP OR PAUSE)

**Top agents, verbatim:**
- “That was probably them. That's not us. At least let us give it the opportunity to know what your options are. Then you can decide what you want to receive.”
- “I understand, but groceries are not getting any cheaper. You are already approved for up to three meals at no cost, and this is included in your Medicaid. I just need to confirm which borough you're in.”

### `personal_info` — I'm not giving you my information
*Seen on 1.7% of calls · 52.6% of those calls were lost · weight 80 · unlocks at level 3*

**Consumer says:**
- “Oh, man. Why do you need my location?”
- “You just asked me my zip code. I told you that's no longer my zip code.”
- “What address do you have?”
- “I'm not going to give you no information. Okay?”

**Official rebuttal (from script.md → “Who are you with?”):** Oh I'm with America's Health. We work with over 25 different health insurance providers. We're just here to facilitate your free review. I just need to confirm... (GO RIGHT BACK WHERE YOU WERE, DON'T STOP OR PAUSE)

**Top agents, verbatim:**
- “No, I'm not allowed to look at your address. My job is only just to make sure you're still within the same borough and that your zip code is correct and that you have your Medicaid.”
- “No, I wouldn't even dare to even ask you that for that, ma'am, I do not need that.”

### `already_signed_up` — I already signed up with someone
*Seen on 1.7% of calls · 62.5% of those calls were lost · weight 79 · unlocks at level 2*

**Consumer says:**
- “I'm already set up, sir. Thank you.”
- “I had a conversation with a young lady earlier today about this.”
- “I spoke with somebody regarding this, and if I need it, I'm gonna call you back, okay?”
- “Already did it, sir. I'm good.”

**Official rebuttal (from script.md → “I already have it”):** Yes of course, we were just calling about the EXTRA support, this isn't a replacement. There's actually been some NEW varieties that JUST released so MORE food coming in, we just need to activate it. I just need to confirm... (GO RIGHT BACK WHERE YOU WERE, DON'T STOP OR PAUSE)

**Top agents, verbatim:**
- “I hear that. But why say no to something you qualify for? I mean, this could be completely different. Just give them a minute to go over it with you.”

### `dont_like_meals` — I don't like the meals
*Seen on 1.7% of calls · 58.2% of those calls were lost · weight 77 · unlocks at level 2*

**Consumer says:**
- “No, I don't want. No. No food.”
- “Well, I'm not interested in the meal service. I do my cooking for myself, thank you very much.”
- “Because I know I won't eat it.”
- “I don't really trust people making my food. I like to prepare from my own food.”

**Official rebuttal (from script.md → “I don't like the meals”):** I understand, but there's actually some new varieties that JUST became available. From healthier options to hot, cold and frozen. Just let the screener know when we get there. I just need to confirm... (GO RIGHT BACK WHERE YOU WERE, DON'T STOP OR PAUSE)

**Top agents, verbatim:**
- “At this time we are only giving out the pre made meals and the vegetables and fruits.”
- “Well, you can get it and give it to someone, whoever you want to give it to. Just give us a few moments to go over this with you.”

### `scam` — This sounds like a scam
*Seen on 1.1% of calls · 52.8% of those calls were lost · weight 65 · unlocks at level 3*

**Consumer says:**
- “I just don't understand why it's in my name. Is this a scam?”
- “I don't know if this is scheme or not. I'll call my insurance.”
- “So you don't know where I live, so you basically use it to cool call... then this is a cool call by somebody else.”
- “I've been on the FBI for more than 10 years... You're trying to hack people, and I will try to get you down.”

**Official rebuttal (from script.md → “Who are you with?”):** Oh I'm with America's Health. We work with over 25 different health insurance providers. We're just here to facilitate your free review. I just need to confirm... (GO RIGHT BACK WHERE YOU WERE, DON'T STOP OR PAUSE)

**Top agents, verbatim:**
- “I understand your skepticism You'd be surprised how often I get to hear something similar to what you just described.”

### `how_much_cost` — How much is this going to cost me?
*Seen on 0.7% of calls · 30.4% of those calls were lost · weight 20 · unlocks at level 1*

**Consumer says:**
- “And how much money. How much is it going to cost me?”
- “The course is very expensive. I can't afford it. Thank you very much.”
- “How. How much is. Is it called mom's meal?”
- “But, but do I have to, do I have to pay or. No.”

**Official rebuttal (from script.md → “How much is this going to cost me?”):** Oh this is ALREADY included in your Medicaid. There is NO cost to you and there is NO change to your coverage. You're ALREADY preapproved for up to 3 meals a day, delivered to your doorstep, I just need to activate it. I just need to confirm... (GO RIGHT BACK WHERE YOU WERE, DON'T STOP OR PAUSE)

**Top agents, verbatim:**
- “No, no, no, no. You don't have to pay for it.”
- “No, sir, not a single penny”

---

## Difficulty levels

| Level | Name | Objections | Temperament |
|--:|---|:-:|---|
| 1 | Warm Up | 0–1 | polite, a little confused about what the call is, cooperative |
| 2 | Guarded | 1–2 | guarded and busy, wants to get off the phone, answers in a few words |
| 3 | Skeptical | 2–3 | skeptical, thinks there is a catch, asks what it costs and who you are |
| 4 | Hostile | 3–4 | rude and impatient, interrupts, may swear, gives you seconds not minutes |
| 5 | Nightmare | 3–5 | difficult: hard of hearing, repeats questions, confused about what coverage they have, may accuse you of scamming, may demand you stop calling |

---

## The Medicaid branch — the persona field that matters most

Every persona answers the Medicaid question one of five ways. This is the core of the vertical:

| `medicaid_status` | The consumer says | Correct handling |
|---|---|---|
| `yes` | "Yes, I have Medicaid." | Continue |
| `no` | "No, I don't have that." | DQ line, end the call |
| `dont_know` | "I don't know, honestly." | **Ask the copay fallback** — free/state = continue, copays = DQ |
| `names_plan` | "I have Healthfirst." | Qualifying plan = continue; bare parent brand = establish which |
| `medicare_only` | "I have Medicare, not Medicaid." | DQ |

**About 1 in 5 personas does not know.** That is deliberate and it matches the real calls. The copay fallback
("when you go to the doctor, do you have to pay anything, or is it usually free?") is the single most
misplayed moment on this campaign, so the simulator has to make trainees meet it often.

### The ten qualifying plans

`Healthfirst` · `Fidelis` · `MetroPlus` · `Emblem` · `VNS` · `Molina` · `UHC Community` · `Anthem HealthPlus` · `Aetna Better Health` · `Amida Care`

Bare parent brands do **not** qualify: `United` · `Aetna` · `Anthem` · `Cigna` · `Humana`. Those companies sell
Medicaid and ordinary plans alike, so the agent must establish which one.

---

## Boroughs, and the traps

Service area is `Bronx` · `Brooklyn` · `Manhattan` · `Queens` · `Staten Island` only.

| Consumer says | Qualifies | Why |
|---|:-:|---|
| Long Island | No | Long Island is outside the five boroughs. |
| Long Island City | Yes | Long Island City is in Queens. |
| New York | Resolve it | Too vague - the agent must establish the borough. |
| Yonkers | No | Westchester, outside the service area. |
| Jersey City | No | New Jersey, outside the service area. |

Give these to personas from level 3 up. Long Island versus Long Island City is the one that catches people.

---

## Disqualifiers

| id | Hidden truth |
|---|---|
| `no_medicaid` | Does not have Medicaid at all. |
| `copays` | Not sure what they have, but pays copays at the doctor - so not Medicaid. |
| `medicare_only` | Has Medicare but no Medicaid. |
| `non_qualifying_plan` | Names a plan that is not on the qualifying list. |
| `outside_nyc` | Lives outside the five boroughs. |

---

## Persona fields

```
name, age (30-85), gender, voice, quirk, temperament
borough            # the truth
says_borough       # what they tell the agent - may be a trap
zip
medicaid_status    # yes | no | dont_know | names_plan | medicare_only
pays_copays        # only consulted on the dont_know branch
plan_named         # a qualifying plan, or a bare parent brand
objections[], will_demand_dnc, correct_outcome, dq_reason
```

The agent's dialer screen shows **name, borough and ZIP only** — and note the borough on screen is what the
list says, which is not always what the consumer will tell them.

---

## Quirks

- hard of hearing, asks you to repeat everything
- TV loud in the background
- grandchildren shouting
- answers in two or three words at a time
- thinks you are calling about their medication
- has had three of these calls this week
- speaks English as a second language and needs you to slow down
- sounds unwell and tires quickly
- keeps asking if this costs money
- says their daughter handles all their benefits
- is cooking and distracted
- was asleep
- very chatty about their neighbourhood

Consumers on this campaign are NYC Medicaid recipients: often older, often hard of hearing, often
speaking English as a second language. Short answers of two or three words are normal, not rude.
