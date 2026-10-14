# Meals Script (verbatim — source: America's Health Medicaid meal service script, supplied by Nate 2026-10-07)

## Opening
Hey, good morning/afternoon (Customer's Name)?
Hey, good morning/afternoon (Customer's Name), this is _______, I'm with America's Health on a recorded line.
We were just giving you a quick call today about the Medicaid meal service for you and your family.
I just need to confirm you ARE still receiving Medicaid, correct? - WAIT FOR THE ANSWER

## Medicaid branch
- YES - (continue)
- NO - 'Oh there must have been a mistake, thank you for your time, have a great day' (END AND CODE AS DQ)
- I DON'T KNOW - 'Okay, well let me ask you this, when you go to the doctor, do you have to pay anything, or is it usually free?'
- YES / IT'S FREE / IT'S FROM THE STATE - (continue)
- NO / I HAVE COPAYS - 'Oh there must have been a mistake, thank you for your time, have a great day' (END AND CODE AS DQ)
- Names a qualifying plan - (continue: Healthfirst, Fidelis, MetroPlus, Emblem, VNS, Molina, UHC Community, Anthem HealthPlus, Aetna Better Health or Amida Care)

## Borough and ZIP
Okay perfect, and you're still in (Customer's Borough), with the zip code (Customer Zip Code), correct? - WAIT FOR THE ANSWER

## Double confirm
Okay LAST question here, JUST to double confirm, you ARE receiving Medicaid and you ARE still in (Customer's Borough), correct? - WAIT FOR THE ANSWER

## Ask for the transfer
Perfect, that's everything I need. The screener is coming on the line now to help you set that up, once that's finished, they'll have you on your way, okay? - AFFIRM - MUST GET PERMISSION TO TRANSFER

## Warm handoff
Hey agent, I have (Customer's Name) in (Customer's Borough), the zip code for them is (Customer's Zip).
Can you take the call from here? Okay, I'll drop the line and let you two finish up, you both have a great day!

---

# Meals Rebuttals

**Not interested**
I understand, but groceries are NOT getting any cheaper. You are ALREADY approved for up to three meals at NO cost and this is INCLUDED in your Medicaid. I just need to confirm... (GO RIGHT BACK WHERE YOU WERE, DON'T STOP OR PAUSE)

**I'm busy / Call me back**
Oh let me assure you this will be SUPER brief, I just have 3 SUPER quick questions and I'll bring the screener on. I just need to confirm... (GO RIGHT BACK WHERE YOU WERE, DON'T STOP OR PAUSE)

**I already have it**
Yes of course, we were just calling about the EXTRA support, this isn't a replacement. There's actually been some NEW varieties that JUST released so MORE food coming in, we just need to activate it. I just need to confirm... (GO RIGHT BACK WHERE YOU WERE, DON'T STOP OR PAUSE)

**I don't need it**
I understand, but with groceries and the cost of living going up, having three meals delivered at NO cost can take a little pressure off your grocery budget. And if you don't personally need them, you could ALWAYS give them to someone in your family or someone who could use the help. You're already approved for it, so there's nothing extra you're paying for. I just need to confirm... (GO RIGHT BACK WHERE YOU WERE, DON'T STOP OR PAUSE)

**I don't like the meals**
I understand, but there's actually some new varieties that JUST became available. From healthier options to hot, cold and frozen. Just let the screener know when we get there. I just need to confirm... (GO RIGHT BACK WHERE YOU WERE, DON'T STOP OR PAUSE)

**How much is this going to cost me?**
Oh this is ALREADY included in your Medicaid. There is NO cost to you and there is NO change to your coverage. You're ALREADY preapproved for up to 3 meals a day, delivered to your doorstep, I just need to activate it. I just need to confirm... (GO RIGHT BACK WHERE YOU WERE, DON'T STOP OR PAUSE)

**What is this / Why are you calling me?**
This is a meal delivery benefit included in your Medicaid. It provides up to 3 meals daily at no cost, and no change to coverage. I just need to confirm... (GO RIGHT BACK WHERE YOU WERE, DON'T STOP OR PAUSE)

**Who are you with?**
Oh I'm with America's Health. We work with over 25 different health insurance providers. We're just here to facilitate your free review. I just need to confirm... (GO RIGHT BACK WHERE YOU WERE, DON'T STOP OR PAUSE)

**DQ line**
Oh there must have been a mistake, thank you for your time, have a great day. (END CALL AND CODE AS DQ)

---

# Qualifying rules

The agent MUST ASK the Medicaid question. Never asking it is the single most serious failure on this
campaign — it is treated as fraud, not as a missed step.

**M1 — Medicaid confirmed.** Qualifies three ways:
1. A direct yes.
2. "I don't know" → the copay fallback question → free / from the state = qualified; copays = DQ.
3. The consumer names a qualifying plan: Healthfirst, Fidelis, MetroPlus, Emblem, VNS, Molina,
   UHC Community, Anthem HealthPlus, Aetna Better Health, Amida Care.

A bare parent brand ("United", "Aetna", "Anthem") is NOT a qualifying plan on its own — those companies
sell Medicaid and non-Medicaid plans, so the agent has to establish which. Medicare only, with no
Medicaid, does not qualify.

**M2 — Borough.** Only the five New York City boroughs: Bronx, Brooklyn, Manhattan, Queens, Staten Island.
"Long Island" on its own is outside the service area. "Long Island City" is in Queens and qualifies.
A bare "New York" or "NYC" is not specific enough — the agent must establish the borough.

**M3 — Double confirm.** Medicaid AND borough confirmed again as the last question before the transfer.
A single combined sentence counts, as long as it is a separate, deliberate ask after the first pass.

---

# Compliance Guidelines

Required on every call, including inbound, callbacks and immediate re-dials:
1. Confirm the correct person on the line.
2. Company name: America's Health.
3. State that the call is on a recorded line.
4. Explain what the call is about: the Medicaid meal service.
5. ASK the Medicaid question and get an answer.
6. Confirm the borough and ZIP code.
7. Double-confirm Medicaid AND borough.
8. Get permission to transfer — the consumer must affirm.
9. Warm handoff naming the consumer, the borough and the ZIP.

What the script approves you to say: up to 3 meals a day, at no cost, included in their Medicaid,
delivered to their doorstep, no change to their coverage, and that new varieties are available.

Never promise anything beyond that. Do not invent delivery dates, meal choices, dollar values, or
eligibility the screener has not confirmed. Do not tell a consumer they qualify — the script's wording is
that they are already approved for the benefit under their Medicaid, which is not the same as promising
them a specific outcome from this call.

Never say the service costs money, and never say it changes their Medicaid coverage. Both are untrue and
both lose the consumer.

---

# Dispositions

**Transfer** — the screener took the call and the warm handoff was completed.
**DQ** — no Medicaid, copays at the doctor, a non-qualifying plan, or outside the five boroughs. Read the
DQ line and end the call.
**Not Interested** — the consumer declines, or hangs up after acknowledging who they are.
**DNC** — the consumer asks to stop being called, asks to be removed from the list, or threatens.
Read the disclaimer, end the call, do not rebuttal.
**Call Back** — the consumer asks to be called back.
**Hung-Up Transfer** — the consumer hangs up after the transfer was started.
**Dead Air** — no response at all.
**Answering Machine** — voicemail. Do not leave a message.
**Wrong Number** — the person asked for is not at this number.
**No English** — the consumer cannot continue in English.
