# ACA objection library and personas

Every objection the simulated consumer can raise, drawn from **3,979 analysed ACA calls** (Sept–Oct 2026).

That export was **transfers only**, so there is no "lost the call" rate. The second term is how often agents
handled the objection **badly** (weak or missed), which makes practice lean on what agents actually fumble:

```
weight = seen_pct * 10 + badly_handled_pct * 1.5
```

| # | id | What the consumer means | Seen % | Badly handled % | Weight | Min level |
|--:|---|---|--:|--:|--:|--:|
| 1 | `what_is_subsidy` | What is the subsidy? What kind of assistance? | 17.4 | 8.5 | 187 | 1 |
| 2 | `already_have_marketplace` | I already have Marketplace / Obamacare | 12.3 | 7.4 | 134 | 1 |
| 3 | `hesitation` | I need to think about it | 10.6 | 11.7 | 124 | 2 |
| 4 | `busy_callback` | I'm busy / call me back | 8.7 | 10.8 | 103 | 1 |
| 5 | `already_have_it` | I already have that | 7.8 | 38.5 | 136 | 1 |
| 6 | `dont_know_what_i_have` | I don't know what insurance I have | 7.7 | 25.7 | 116 | 1 |
| 7 | `company_question` | Who are you with? | 7.1 | 6.2 | 80 | 1 |
| 8 | `where_calling_from` | Where are you calling from? | 5.9 | 7.7 | 71 | 2 |
| 9 | `scam` | This is a scam | 4.5 | 11.3 | 62 | 3 |
| 10 | `wrong_state` | Wrong state | 3.6 | 16.8 | 61 | 2 |
| 11 | `is_it_money` | Is it money? A card? Help with bills? | 3.3 | 14.4 | 55 | 2 |
| 12 | `insurance_not_familiar` | They name a carrier | 2.7 | 12.7 | 46 | 2 |
| 13 | `told_not_qualified` | They told me I don't qualify | 2.4 | 4.9 | 31 | 2 |
| 14 | `personal_info` | I'm not giving personal information | 2.0 | 10.1 | 35 | 3 |
| 15 | `how_got_info` | How did you get my information? | 2.0 | 26.9 | 60 | 3 |
| 16 | `not_interested` | I'm not interested | 2.0 | 9.2 | 34 | 1 |
| 17 | `dont_want_change` | I don't want to change my insurance | 1.6 | 20.0 | 46 | 2 |
| 18 | `didnt_apply` | I didn't apply for that | 1.5 | 9.7 | 30 | 2 |
| 19 | `no_subsidy_exists` | There is no subsidy / it was shut down | 1.2 | 9.3 | 26 | 3 |
| 20 | `caller_id` | Your caller ID says scam likely | 1.1 | 18.5 | 39 | 3 |
| 21 | `not_enough_income` | I don't have enough income | 0.5 | 10.0 | 20 | 2 |
| 22 | `tax_question` | Why the tax question? | 0.3 | 7.7 | 15 | 3 |

Note `already_have_it` (badly handled 38.5%) and `dont_know_what_i_have` (25.7%). Neither is the most
common objection, and both outrank far more frequent ones on draw weight for that reason.

---

## Per objection

Real consumer phrasings from the calls, the official rebuttal (pulled live from `script.md`), and what top
agents actually said. Top-agent lines are filtered: anything containing a promise or a banned word was
dropped rather than shown to a trainee as an example worth copying.

### `what_is_subsidy` — What is the subsidy? What kind of assistance?
*Seen on 17.4% of calls · handled badly 8.5% of the time · weight 187 · unlocks at level 1*

**Consumer says:**
- “What is this for?”
- “What is it?”
- “I don't even know what that is.”

**Official rebuttal (from script.md → “What is the subsidy / Any questions in regards to the subsidy”):** Great question — there's so many benefits and incentives available, it's different for everyone. It depends on your specific situation, your wants and your needs. Once I confirm what I have, the worker in your area can provide the options they may have available for you. Let's finish this part first, you're still living in (STATE) with no active Medicare, Medicaid, or Work Insurance correct?

**Top agents, verbatim:**
- “There are benefits, (customer's name), that's not included in any healthcare plan. Now, which ones you may qualify for, I don't know the local agent is going to be joining in with their status. But they are benefits that you can't just go out and buy.”
- “There's health subsidies, and there's benefit packages that come through the Affordable Care Act, and everybody's situations, wants, and needs are different. So everybody's benefits are different.”

### `already_have_marketplace` — I already have Marketplace / Obamacare
*Seen on 12.3% of calls · handled badly 7.4% of the time · weight 134 · unlocks at level 1*

**Consumer says:**
- “Marketplace.”
- “I have marketplace.”
- “I got a marketplace.”

**Official rebuttal (from script.md → “I already have Marketplace / Obamacare / ACA”):** And that's why we are calling today — there are new benefits that have come out that you may not be receiving as of yet. Just want to be sure you don't have Medicare, Medicaid or work insurance still, to be able to assist you.

**Top agents, verbatim:**
- “All right. Is that through the marketplace?  Okay, that's what I see here. And that's okay. You can have that.”
- “Yeah, we know. We see that. We're friendly with Marketplace. That's great, but no work insurance, Medicare, Medicaid or none of that stuff, right?”

### `hesitation` — I need to think about it
*Seen on 10.6% of calls · handled badly 11.7% of the time · weight 124 · unlocks at level 2*

**Consumer says:**
- “I don't know.”
- “Say it again.”
- “I'm sorry, what?”

**Official rebuttal (from script.md → “Anything not listed above”):** That's a great question, we will find out as soon as the worker gets on the line for accurate details. I'll quickly finish this part first — you're still living in (STATE) with no active Medicare, Medicaid, or Work Insurance correct?

**Top agents, verbatim:**
- “They got you working hard on a Friday thank you for your service, (customer's name). I know it's not an easy job.”
- “No. I'm asking you to make sure you can confirm that you have no Medicaid, Medicare, or work insurance. Because these benefits are for help the people that do not receive any Medicaid, Medicare or work insurance.”

### `busy_callback` — I'm busy / call me back
*Seen on 8.7% of calls · handled badly 10.8% of the time · weight 103 · unlocks at level 1*

**Consumer says:**
- “I'm actually about to head into work right now. Could you call me back at a later day.”
- “Yeah, I need to go. They... trying to stop there way early that Friday afternoon.”
- “I'm actually busy. I just got to work. Could I get a call back later on?”

**Official rebuttal (from script.md → “I'm at work / I can't talk / I'm busy / Can you call me back?”):** No problem at all, I'll be quick. I just need to confirm if you still live in (STATE) and don't have Medicare, Medicaid, or employer insurance.

**Top agents, verbatim:**
- “I got you. So this takes a couple minutes, but I'm just going to get us a faster line because we don't need to sit and hold.”
- “You don't have to do anything. You just talk to the benefits coordinator and that's it you either decide you want them or not. It's that simple.”

### `already_have_it` — I already have that
*Seen on 7.8% of calls · handled badly 38.5% of the time · weight 136 · unlocks at level 1*

**Consumer says:**
- “I have insurance.”
- “I got Medicaid.”
- “I have Medicaid.”

**Official rebuttal (from script.md → “I already have Marketplace / Obamacare / ACA”):** And that's why we are calling today — there are new benefits that have come out that you may not be receiving as of yet. Just want to be sure you don't have Medicare, Medicaid or work insurance still, to be able to assist you.

**Top agents, verbatim:**
- “And that's through the marketplace, right?  that's fine. I see that. That's absolutely fine.”
- “Yes. Do you have it through Marketplace?”

### `dont_know_what_i_have` — I don't know what insurance I have
*Seen on 7.7% of calls · handled badly 25.7% of the time · weight 116 · unlocks at level 1*

**Consumer says:**
- “I have no idea.”
- “I don't know.”
- “I think so.”

**Official rebuttal (from script.md → “I don't know what I have”):** I can help you figure it out. Medicare is usually for folks over the age of 65, it comes with a red, white, and blue card. Medicaid, you would have had to apply and get approved for. And work insurance is provided by your employer, you'd see that deducted off your paycheque. You have none of those, right?

**Top agents, verbatim:**
- “Good. Yeah, that's why we're calling. Because you don't have work insurance either, right?”
- “Well, no, no, no. It's just having a certain kind of insurance. Do you just have Marketplace or do you have work insurance, Medicare and Medicaid?”

### `company_question` — Who are you with?
*Seen on 7.1% of calls · handled badly 6.2% of the time · weight 80 · unlocks at level 1*

**Consumer says:**
- “Who is this?”
- “Who's this?”
- “Who's calling?”

**Official rebuttal (from script.md → “What is America's Health / Health Benefits Centre?”):** We help you to receive benefits through the Affordable Care Act. The incentives are provided to you for living in (STATE) with no active Medicare, Medicaid, or Work Insurance, are these details still up to date for you?

**Top agents, verbatim:**
- “This is Cheryl on a recorded line with America's Health. It's a quick reminder regarding your subsidies under the Affordable Care Act.”
- “America, America's health.”

### `where_calling_from` — Where are you calling from?
*Seen on 5.9% of calls · handled badly 7.7% of the time · weight 71 · unlocks at level 2*

**Consumer says:**
- “Who is this?”
- “Who's calling?”
- “Where are you at?”

**Official rebuttal (from script.md → “Anything not listed above”):** That's a great question, we will find out as soon as the worker gets on the line for accurate details. I'll quickly finish this part first — you're still living in (STATE) with no active Medicare, Medicaid, or Work Insurance correct?

**Top agents, verbatim:**
- “Hi, this is Cheryl on a recorded line with America's Health. We notice you haven't received your subsidies under the Affordable Care Act.”
- “My name is Sarah. I work for America's Health. We are on a recorded line. We do the benefit allowances that are through Affordable Care Act.”

### `scam` — This is a scam
*Seen on 4.5% of calls · handled badly 11.3% of the time · weight 62 · unlocks at level 3*

**Consumer says:**
- “I done filled out 50 applications of the subsidy loan stuff, man. It's starting to sound like a scam to me.”
- “Is this real?”
- “Is this the J. Is not some kind of scam.”

**Official rebuttal (from script.md → “This is a scam / spam call / That doesn't sound true”):** I get it, Mr./Mrs. (Customer's Name). I completely understand why you'd be cautious, a lot of people feel that way at first. For your peace of mind, I can assure you this is legit, it isn't a scam call. I don't need any personal or financial information from you, so there's nothing here for me to misuse. The purpose is simply to verify whether you still live in (STATE) and currently have no Medicare, Medicaid, or employer coverage. For you to see which benefits you could possibly get, are those details still accurate for you?

**Top agents, verbatim:**
- “No, ma'am, is real. I'm a real person. We have a real company. We do real benefits and subsidy allowances through the Affordable Care Act. That's real. I base my character on my being honest, so I'm honestly telling you what's going on.”
- “It's not, though. It really isn't. We have a real company, real website. We're real people. There is scams out there but we're not one of them.”

### `wrong_state` — Wrong state
*Seen on 3.6% of calls · handled badly 16.8% of the time · weight 61 · unlocks at level 2*

**Consumer says:**
- “I'm in arkansas.”
- “No, I'm in arkansas.”
- “I'm in louisiana.”

**Official rebuttal (from script.md → “Wrong state”):** That could be the issue. What state can I get you help in? (edit and save on the Call Info page)

**Top agents, verbatim:**
- “You live in Oklahoma. Okay, that's okay. I'll update your state real quick for you because the benefits are quite different in each state, so give me a second to update that.”
- “You're in Illinois. Okay, let me update that for you real quick, because the benefits are completely different for that I'm just double confirming now. Illinois over 19, no Medicaid, Medicare, work insurance.”

### `is_it_money` — Is it money? A card? Help with bills?
*Seen on 3.3% of calls · handled badly 14.4% of the time · weight 55 · unlocks at level 2*

**Consumer says:**
- “Are you talking about. Money? Any insurance or something like that? I don't know what?”
- “Do I have to pay anything for anything?”
- “It's something that costs money. So it's like, if it costs money, I can't do it because I have none.”

**Official rebuttal (from script.md → “What am I going to get / Is it money, cash, cheque, card, help with bills or groceries?”):** Since it's confidential between the licensed agent and yourself, once I verify everything you can go ahead and ask the licensed agent. Now, do you still not receive any Medicare, Medicaid or work insurance?

**Top agents, verbatim:**
- “No, this is a free government provided allowance. I don't have any questions for you. Nothing personal, nothing financial.”
- “I'm not sure. I don't want to promise you something and then it not happen. I honestly don't know. But if you've read something, that may be accurate.”

### `insurance_not_familiar` — They name a carrier
*Seen on 2.7% of calls · handled badly 12.7% of the time · weight 46 · unlocks at level 2*

**Consumer says:**
- “This is for insurance.”
- “I don't know what you're talking about.”
- “I received this one card before... It said Anthem on it was like blue and white. Would that be the car you're referring to?”

**Official rebuttal (from script.md → “They name an insurance you are not familiar with”):** It is through the Marketplace, correct? (NO or cannot confirm = DQ. YES = proceed with the transfer)

**Top agents, verbatim:**
- “Asked if he'd heard of Obamacare, explained it's the same thing as the Affordable Care Act, said the benefits coordinator would go over the subsidy and benefit allowances, it takes a couple of minutes, and there's no pressure to take it.”
- “So it's for people that don't have Medicare, Medicaid, or work insurance. You're not receiving any of those?”

### `told_not_qualified` — They told me I don't qualify
*Seen on 2.4% of calls · handled badly 4.9% of the time · weight 31 · unlocks at level 2*

**Consumer says:**
- “That's what the last agent explained to me.”
- “No, when my agent said I shouldn't be receiving anything.”
- “I was disqualified.”

**Official rebuttal (from script.md → “They told me I'm not qualified (no job / make too much / not available in my area)”):** That's exactly why they asked me to call you, I will take care of this immediately. The benefits and incentives are provided to help you for living in (STATE) with no active Medicare, Medicaid, or Work Insurance, are these details still up to date for you?

**Top agents, verbatim:**
- “Okay, no worries. Well, this program is for to help those people that have no Medicaid, Medicare, or work insurance.”
- “You don't have to be. If you would have been on disability, you wouldn't qualify. It's only for people who are not on those that may qualify.”

### `personal_info` — I'm not giving personal information
*Seen on 2.0% of calls · handled badly 10.1% of the time · weight 35 · unlocks at level 3*

**Consumer says:**
- “Do you have my address?”
- “Ma'am, if you. I already gave you all this information. You know that it's right in front of your computer. You know, I'm 47 years old.”
- “Present my credit card.”

**Official rebuttal (from script.md → “I'm not giving you my personal information”):** I don't need any personal or financial information from you. The purpose is simply to verify whether you still live in (STATE) and currently have no Medicare, Medicaid, or employer coverage. For you to see which benefits you could possibly get, are those details still accurate for you?

**Top agents, verbatim:**
- “No, no, no, no, no. No proof of residence or anything like that. As long as you're still in Texas, 77022 is the zip code I see for you.”
- “I'm not asking you for any personal information, Rufus. Not none. I'm just asking you to confirm that you're in South Carolina with no Medicaid, Medicare, work insurance.”

### `how_got_info` — How did you get my information?
*Seen on 2.0% of calls · handled badly 26.9% of the time · weight 60 · unlocks at level 3*

**Consumer says:**
- “And how did you get my number?”
- “So who told you to get in contact with me?”
- “I just got my number changed to this number right here... I never talked to anyone on this number.”

**Official rebuttal (from script.md → “How did you get my information / Where did you get my information?”):** I do have you here for a reason — we are actually only calling those that may qualify for a free subsidy with the Affordable Care Act. Now, you do not receive Medicare or Medicaid or any work insurance, correct?

**Top agents, verbatim:**
- “I don't have very much information. I just have your name and your state. This comes through the Affordable Care act or it can come through somebody trying to see if they qualify for something.”
- “It's national. It's in every state. It's all 50 states. It's the Affordable Care Act I don't have any of your information except your name and the state you're in.”

### `not_interested` — I'm not interested
*Seen on 2.0% of calls · handled badly 9.2% of the time · weight 34 · unlocks at level 1*

**Consumer says:**
- “No, thank you.”
- “Oh, lord, I don't want to.”
- “I just keep getting calls from so many people and it's annoying as hell... I don't care to answer any more questions.”

**Official rebuttal (from script.md → “I'm not interested / I don't want that”):** This isn't a matter of interest. The benefits and incentives are provided to help you for living in (STATE) with no active Medicare, Medicaid, or Work Insurance, are these details still up to date for you?

**Top agents, verbatim:**
- “It takes a couple minutes of your time, if you have that, to just find out what they may be, it may be worth your while.”
- “Oh, okay, so you don't want to see which ones you may qualify for? Yeah, it's super brief. It doesn't hurt.”

### `dont_want_change` — I don't want to change my insurance
*Seen on 1.6% of calls · handled badly 20.0% of the time · weight 46 · unlocks at level 2*

**Consumer says:**
- “I'm okay. I don't want to say nothing out right now.”
- “I don't want to speak to anybody because the last time I did, they changed my insurance.”
- “Now, this won't affect my plan, right? ... Like the United Healthcare gold plan.”

**Official rebuttal (from script.md → “I don't want to change my insurance / Will this change my plan?”):** You'd be the one to change anything, I can't do that. The purpose is simply to verify whether you still live in (STATE) and currently have no Medicare, Medicaid, or employer coverage.

**Top agents, verbatim:**
- “No, only you have the power to change your insurance, sweetheart. Not me, not anybody else, just you.”
- “That's okay. You just let them know that that's not a problem. That only you have the power to do that.”

### `didnt_apply` — I didn't apply for that
*Seen on 1.5% of calls · handled badly 9.7% of the time · weight 30 · unlocks at level 2*

**Consumer says:**
- “Yeah, I didn't sign up for none of that.”
- “I'm so confused. Like, I never put my information in any online or whatever... how they get my number.”
- “No, not here.”

**Official rebuttal (from script.md → “I didn't apply for that”):** That's right, you don't apply for it. The benefits and incentives are provided to help you for living in (STATE) with no active Medicare, Medicaid, or Work Insurance, are these details still up to date for you?

### `no_subsidy_exists` — There is no subsidy / it was shut down
*Seen on 1.2% of calls · handled badly 9.3% of the time · weight 26 · unlocks at level 3*

**Consumer says:**
- “My agent says that there is no such thing. If there's a. Is such a thing. You've got all my information, mail it to me.”
- “I ain't waiting on that because the marketplace already told me I wasn't getting no such... they paying for my premium right now.”
- “I always get told that... I look to see what there is, and it just takes me a silly circle and nothing ever comes from it.”

**Official rebuttal (from script.md → “There is no subsidy / The government shut it down”):** I understand your concern. There's a lot of confusing information out there. Luckily, it doesn't affect this benefit at all. This is about the assistance you haven't claimed. You're still living in (STATE) with no active Medicare, Medicaid, or Work Insurance correct?

**Top agents, verbatim:**
- “Offered to get the benefits coordinator on the line to find out what's going on with her case, said it takes a couple minutes, then moved into the confirmations.”

### `caller_id` — Your caller ID says scam likely
*Seen on 1.1% of calls · handled badly 18.5% of the time · weight 39 · unlocks at level 3*

**Consumer says:**
- “Are you in new smyrna beach? The call's coming in from new smyrna beach.”
- “Now? I'm picking out from your number. It's a Mississippi.”
- “I think you're calling me from a Texas number... but you calling me from a Texas number also.”

**Official rebuttal (from script.md → “This is a scam / spam call / That doesn't sound true”):** I get it, Mr./Mrs. (Customer's Name). I completely understand why you'd be cautious, a lot of people feel that way at first. For your peace of mind, I can assure you this is legit, it isn't a scam call. I don't need any personal or financial information from you, so there's nothing here for me to misuse. The purpose is simply to verify whether you still live in (STATE) and currently have no Medicare, Medicaid, or employer coverage. For you to see which benefits you could possibly get, are those details still accurate for you?

**Top agents, verbatim:**
- “Because that's our corporate office. I work from home now, since Covid. That's what comes out of our database. But I live in Sarasota, Florida.”
- “Yep, yep. It should be the same number and warned that if she doesn't double confirm Medicare, Medicaid, work insurance twice, it's not us, hang up.”

### `not_enough_income` — I don't have enough income
*Seen on 0.5% of calls · handled badly 10.0% of the time · weight 20 · unlocks at level 2*

**Consumer says:**
- “I am of the age of 21, but on top of that I do not have currently steady paying jobs.”
- “I don't have those, but I also don't have a job, and I know that I have to have a job to qualify.”
- “I'm in school right now, and I can't really... I can afford, like, nothing right now.”

**Official rebuttal (from script.md → “I don't have enough income for this / I don't receive Medicare or Medicaid”):** Sir/Ma'am, this is only to help those that do not receive Medicare, Medicaid or work insurance — the Affordable Care Act is free health insurance.

### `tax_question` — Why the tax question?
*Seen on 0.3% of calls · handled badly 7.7% of the time · weight 15 · unlocks at level 3*

**Consumer says:**
- “The person that I spoke to said that as long as I was making under 24,000 a year, that this is an available availability to me.”
- “Do you help out with anything, like late tax routine returns or anything?”
- “Well, I don't want to come back and charge me before on my taxes or nothing like that, you know.”

**Official rebuttal (from script.md → “Anything not listed above”):** That's a great question, we will find out as soon as the worker gets on the line for accurate details. I'll quickly finish this part first — you're still living in (STATE) with no active Medicare, Medicaid, or Work Insurance correct?

---

## Difficulty levels

| Level | Name | Objections drawn | Temperament |
|--:|---|:-:|---|
| 1 | Warm Up | 0–1 | polite, a little distracted, cooperative once they understand |
| 2 | Guarded | 1–2 | guarded, impatient, wants to get off the phone |
| 3 | Skeptical | 2–3 | skeptical, sharp, asks pointed questions, won't give information easily |
| 4 | Hostile | 3–4 | rude and hostile, interrupts, curses at the agent, sarcastic |
| 5 | Nightmare | 3–5 | unhinged: yells, swears constantly, rambles off-topic, accuses the agent, may be confused or hard of hearing, may try to trap the agent |

Starting patience is `10 - level`.

---

## The nine disqualifiers

Drawn when a persona's correct outcome is DQ. The consumer admits it **only when asked** — never volunteered.

| id | Hidden truth |
|---|---|
| `medicare` | Has Medicare (over 65, has the red, white and blue card). |
| `medicaid` | Has Medicaid. |
| `work_insurance` | Has health insurance through their employer. |
| `spousal_work` | Is covered by their spouse's work insurance. |
| `military_va` | Has VA or military health care. |
| `private` | Has a private plan bought directly from an insurer, not through the Marketplace. |
| `state_insurance` | Has a state health plan. |
| `ssdi` | Receives SSDI disability insurance. |
| `non_citizen` | Is not a US citizen. |

**SSI is not a disqualifier. SSDI is.** A consumer naming a carrier (Blue Cross, Oscar, Ambetter, Anthem,
UnitedHealthcare) is only transferable once the agent confirms the plan is through the Marketplace.

---

## Persona fields

```
name, age (19-64, or 66-78 when the disqualifier is Medicare), gender, voice,
state, zip, temperament, quirk,
has_medicare, has_medicaid, has_work_insurance, other_coverage,
has_marketplace_plan      # ~33% of qualified consumers - NOT a disqualifier
knows_what_they_have      # 10-25% genuinely cannot say what coverage they have
is_us_citizen, objections[], will_demand_dnc,
correct_outcome           # Transfer | DQ | DNC, decided when the persona is built
dq_reason
```

The agent's dialer screen shows **name, state and ZIP only**. Everything else is hidden truth the grader
sees after the call.

---

## Quirks

- kids shouting in the background
- driving, on speakerphone
- at work and can't really talk
- has had six of these calls today
- sounds half asleep
- TV blaring
- keeps asking you to repeat yourself
- very chatty, goes off on tangents about their week
- suspicious of everything, asks what company this is twice
- dog barking the whole call
- phone keeps cutting out
- wants to know if this costs them anything before anything else
- mentions money is tight right now
- says their spouse handles the insurance

---

## Outcome mix by level

| Level | Transfer | DQ | DNC |
|--:|--:|--:|--:|
| 1–2 | 100% | — | — |
| 3–4 | 78% | 22% | — |
| 5 | 55% | 20% | 25% |

Levels 1 and 2 are always transferable so a new hire can learn the flow. From level 3 the agent has to learn
when **not** to transfer, because the wrong outcome cannot pass however smooth the call was.
