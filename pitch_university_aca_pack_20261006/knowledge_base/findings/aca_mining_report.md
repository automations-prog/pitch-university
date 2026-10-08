# ACA call mining

Total analysis cost: $179.96

## all (3979 real conversations of 3981 analysed)

Outcomes: {'callback': 180, 'transferred': 3757, 'unclear': 38, 'consumer_hung_up': 3, 'dq': 1}

### Objections

| id | what they say | seen % | loses % | strong | ok | weak | missed |
|---|---|--:|--:|--:|--:|--:|--:|
| `other` | Anything else | 22.6 | 0.1 | 294 | 622 | 104 | 44 |
| `what_is_subsidy` | What is the subsidy / what kind of assistance is it | 15.7 | 0.2 | 195 | 450 | 40 | 8 |
| `already_have_marketplace` | I already have Marketplace / Obamacare / ACA | 12.2 | 0.0 | 216 | 237 | 28 | 7 |
| `hesitation` | I need to think about it | 10.2 | 0.0 | 87 | 280 | 46 | 7 |
| `busy_callback` | I'm busy, at work, call me back | 8.1 | 0.6 | 108 | 202 | 28 | 10 |
| `already_have_it` | I already have that / I already get it | 7.7 | 0.0 | 91 | 116 | 82 | 23 |
| `dont_know_what_i_have` | I don't know what insurance I have | 7.5 | 0.0 | 71 | 160 | 65 | 11 |
| `company_question` | What is America's Health / who are you with | 6.9 | 0.0 | 110 | 157 | 12 | 4 |
| `where_calling_from` | Where are you calling from / which state / why this coordinator | 5.9 | 0.4 | 91 | 123 | 12 | 7 |
| `scam` | This is a scam / spam / doesn't sound real | 4.2 | 0.0 | 65 | 94 | 17 | 5 |
| `wrong_state` | Wrong state / I don't live there | 3.5 | 0.0 | 80 | 45 | 12 | 7 |
| `is_it_money` | Is it money, cash, a cheque, a card, help with bills or groceries | 3.0 | 0.0 | 45 | 70 | 16 | 2 |
| `insurance_not_familiar` | Names an insurance company (Blue Cross, Oscar, Ambetter, Anthem, UHC...) | 2.7 | 0.0 | 35 | 60 | 10 | 3 |
| `told_not_qualified` | They told me I don't qualify (no job, income, not in my area) | 2.3 | 0.0 | 38 | 54 | 4 | 1 |
| `personal_info` | I'm not giving you my personal information | 1.9 | 0.0 | 31 | 42 | 7 | 1 |
| `not_interested` | I'm not interested / I don't want that | 1.9 | 0.0 | 27 | 49 | 2 | 2 |
| `how_got_info` | How did you get my information | 1.9 | 0.0 | 9 | 49 | 20 | 2 |
| `dont_want_change` | I don't want to change my insurance / will this change my plan | 1.5 | 0.0 | 24 | 26 | 8 | 5 |
| `didnt_apply` | I didn't apply for that | 1.5 | 0.0 | 19 | 36 | 5 | 1 |
| `caller_id` | Why does it say you're calling from another state / scam likely | 1.1 | 0.0 | 7 | 31 | 3 | 3 |
| `no_subsidy_exists` | There is no subsidy / the government shut it down | 1.1 | 0.0 | 10 | 32 | 3 | 1 |
| `not_enough_income` | I don't have enough income for this | 0.5 | 0.0 | 5 | 13 | 1 | 0 |
| `tax_question` | Questions about the tax confirmation | 0.3 | 0.0 | 5 | 7 | 1 | 0 |
| `family_handles` | My son / husband / wife handles this | 0.2 | 0.0 | 6 | 2 | 0 | 0 |
| `dnc_request` | Asked not to be called again | 0.2 | 0.0 | 1 | 2 | 3 | 0 |
| `open_enrollment` | Open enrolment / why are you calling me | 0.2 | 0.0 | 0 | 4 | 1 | 1 |
| `give_to_someone_else` | Give it to someone who needs it more | 0.1 | 0.0 | 0 | 3 | 0 | 0 |

### Script steps

| step | done % | asked but not waited % | skipped % |
|---|--:|--:|--:|
| correct_person | 70.4 | 22.4 | 7.2 |
| company_and_recorded_line | 67.9 | 31.0 | 1.1 |
| aca_mention | 74.2 | 24.4 | 1.4 |
| state_confirm | 75.5 | 22.4 | 2.2 |
| mmw_first | 87.2 | 12.8 | 0.1 |
| age_19 | 76.2 | 21.4 | 2.4 |
| mmw_double | 53.1 | 13.1 | 33.8 |
| verbal_consent | 64.9 | 16.2 | 18.9 |
| warm_handoff_with_state | 93.9 | 2.0 | 4.0 |

### Compliance issues

- **promise_will_get**: 1260 — e.g. "So it may mean you didn't get everything you're entitled to yet."
- **transferred_without_consent**: 1043 — e.g. "I'm now going to grab a licensed agent and they'll let you know what subsidy assistance you may receive to help you out. Okay? Okay."
- **other**: 826 — e.g. "you may be getting additional allowances and support"
- **guaranteed_outcome**: 544 — e.g. "so they can tell you what subsidy benefits you may receive today and when you might receive those benefits"
- **wrong_company_name**: 387 — e.g. "it's Ronisha from American Health"
- **forbidden_financial_word**: 312 — e.g. "whenever there's money involved, something bad's gonna happen"
- **promise_qualify_eligible**: 309 — e.g. "I just need to confirm that you're still in the qualifying state of Mississippi"
- **no_recorded_line**: 171 — e.g. "this is (agent) with America's Health, and I'm calling you in regards to your subsidy allowance through the Affordable Care Act."
- **forbidden_household_word**: 124 — e.g. "what's not fun, actually, this year is the electricity bills. The electricity bill I just got, I kid you not, $350."
- **rude_to_consumer**: 18 — e.g. "No, you didn't get all of them. That's exactly why I'm calling you."
- **pitched_past_dnc**: 5 — e.g. "Stop calling me because. / Oh, no, that wasn't us... This is in regards to your free subsidies."

### Disqualifiers

- consumers with a disqualifier: 239
- caught by the agent: 81
- **transferred anyway: 220**
- types: {'not_marketplace_carrier': 121, 'medicaid': 51, 'ssdi': 11, 'medicare': 12, 'private': 13, 'state_insurance': 6, 'military_va': 4, 'non_citizen': 1, 'work_insurance': 20}

## model (1326 real conversations of 1326 analysed)

Outcomes: {'callback': 69, 'transferred': 1247, 'unclear': 6, 'agent_ended_ni': 1, 'dq': 1, 'consumer_hung_up': 1, 'dnc': 1}

### Objections

| id | what they say | seen % | loses % | strong | ok | weak | missed |
|---|---|--:|--:|--:|--:|--:|--:|
| `other` | Anything else | 17.6 | 0.0 | 55 | 167 | 39 | 13 |
| `what_is_subsidy` | What is the subsidy / what kind of assistance is it | 14.0 | 0.0 | 14 | 161 | 27 | 1 |
| `hesitation` | I need to think about it | 8.1 | 0.0 | 22 | 81 | 7 | 2 |
| `busy_callback` | I'm busy, at work, call me back | 7.7 | 0.0 | 34 | 61 | 8 | 3 |
| `already_have_marketplace` | I already have Marketplace / Obamacare / ACA | 7.2 | 0.0 | 29 | 58 | 7 | 1 |
| `already_have_it` | I already have that / I already get it | 6.9 | 0.0 | 14 | 30 | 35 | 17 |
| `company_question` | What is America's Health / who are you with | 5.5 | 0.0 | 32 | 36 | 6 | 0 |
| `dont_know_what_i_have` | I don't know what insurance I have | 4.8 | 0.0 | 8 | 39 | 17 | 3 |
| `where_calling_from` | Where are you calling from / which state / why this coordinator | 4.1 | 0.0 | 25 | 26 | 3 | 0 |
| `scam` | This is a scam / spam / doesn't sound real | 3.4 | 0.0 | 8 | 38 | 3 | 1 |
| `is_it_money` | Is it money, cash, a cheque, a card, help with bills or groceries | 3.3 | 0.0 | 10 | 29 | 8 | 0 |
| `wrong_state` | Wrong state / I don't live there | 3.2 | 0.0 | 19 | 14 | 11 | 2 |
| `personal_info` | I'm not giving you my personal information | 2.0 | 0.0 | 11 | 14 | 2 | 1 |
| `told_not_qualified` | They told me I don't qualify (no job, income, not in my area) | 1.8 | 0.0 | 7 | 18 | 1 | 0 |
| `how_got_info` | How did you get my information | 1.8 | 0.0 | 4 | 14 | 4 | 2 |
| `not_interested` | I'm not interested / I don't want that | 1.4 | 0.0 | 4 | 9 | 3 | 2 |
| `insurance_not_familiar` | Names an insurance company (Blue Cross, Oscar, Ambetter, Anthem, UHC...) | 1.4 | 0.0 | 3 | 12 | 3 | 0 |
| `dont_want_change` | I don't want to change my insurance / will this change my plan | 0.9 | 0.0 | 9 | 1 | 2 | 0 |
| `didnt_apply` | I didn't apply for that | 0.8 | 0.0 | 0 | 10 | 1 | 0 |
| `caller_id` | Why does it say you're calling from another state / scam likely | 0.8 | 0.0 | 2 | 4 | 4 | 0 |
| `no_subsidy_exists` | There is no subsidy / the government shut it down | 0.6 | 0.0 | 2 | 5 | 1 | 0 |
| `dnc_request` | Asked not to be called again | 0.2 | 50.0 | 0 | 1 | 0 | 1 |
| `give_to_someone_else` | Give it to someone who needs it more | 0.1 | 0.0 | 0 | 1 | 0 | 0 |
| `family_handles` | My son / husband / wife handles this | 0.1 | 0.0 | 0 | 1 | 0 | 0 |
| `not_enough_income` | I don't have enough income for this | 0.1 | 0.0 | 0 | 0 | 1 | 0 |

### Script steps

| step | done % | asked but not waited % | skipped % |
|---|--:|--:|--:|
| correct_person | 57.8 | 22.2 | 19.9 |
| company_and_recorded_line | 66.6 | 33.2 | 0.2 |
| aca_mention | 68.6 | 30.9 | 0.5 |
| state_confirm | 64.7 | 32.3 | 3.0 |
| mmw_first | 86.3 | 13.4 | 0.3 |
| age_19 | 67.9 | 26.6 | 5.5 |
| mmw_double | 34.5 | 7.0 | 58.4 |
| verbal_consent | 43.7 | 14.5 | 41.8 |
| warm_handoff_with_state | 96.8 | 2.0 | 1.2 |

### Compliance issues

- **promise_will_get**: 980 — e.g. "You're in the state of Texas to receive yours, is that still correct?"
- **transferred_without_consent**: 595 — e.g. "All right, give me a second to get you a benefits coordinator."
- **other**: 358 — e.g. "it looks like you've never received your benefits"
- **guaranteed_outcome**: 326 — e.g. "we'll get your case straightened out today for you"
- **forbidden_financial_word**: 237 — e.g. "You can get more for your money out there."
- **promise_qualify_eligible**: 124 — e.g. "when you may be entitled to receive your allowance"
- **wrong_company_name**: 26 — e.g. "Good afternoon. Thank you for calling Ema Marketing."
- **forbidden_household_word**: 21 — e.g. "We had a Jenkins grocery store on MLK in Sarasota, Florida."
- **no_recorded_line**: 9 — e.g. "My name is Sarah. I'm calling from Americas Health and we do the free subsidy and benefit allowances that are through Affordable Care Act."
- **mentioned_pitch_perfect**: 1 — e.g. "You can go on. Indeed. And look up Pitch Perfect. They're hiring."

### Disqualifiers

- consumers with a disqualifier: 83
- caught by the agent: 17
- **transferred anyway: 75**
- types: {'private': 8, 'not_marketplace_carrier': 37, 'medicaid': 23, 'state_insurance': 2, 'ssdi': 5, 'none': 1, 'work_insurance': 1, 'medicare': 6}

## bad (30 real conversations of 30 analysed)

Outcomes: {'transferred': 30}

### Objections

| id | what they say | seen % | loses % | strong | ok | weak | missed |
|---|---|--:|--:|--:|--:|--:|--:|
| `other` | Anything else | 20.0 | 0.0 | 2 | 5 | 2 | 1 |
| `already_have_it` | I already have that / I already get it | 6.7 | 0.0 | 1 | 0 | 2 | 0 |
| `how_got_info` | How did you get my information | 6.7 | 0.0 | 0 | 0 | 1 | 1 |
| `dont_know_what_i_have` | I don't know what insurance I have | 6.7 | 0.0 | 0 | 1 | 0 | 1 |
| `what_is_subsidy` | What is the subsidy / what kind of assistance is it | 6.7 | 0.0 | 1 | 0 | 1 | 0 |
| `already_have_marketplace` | I already have Marketplace / Obamacare / ACA | 3.3 | 0.0 | 0 | 1 | 0 | 0 |
| `company_question` | What is America's Health / who are you with | 3.3 | 0.0 | 0 | 1 | 0 | 0 |
| `caller_id` | Why does it say you're calling from another state / scam likely | 3.3 | 0.0 | 0 | 1 | 0 | 0 |

### Script steps

| step | done % | asked but not waited % | skipped % |
|---|--:|--:|--:|
| correct_person | 80.0 | 13.3 | 6.7 |
| company_and_recorded_line | 83.3 | 6.7 | 10.0 |
| aca_mention | 56.7 | 6.7 | 36.7 |
| state_confirm | 70.0 | 26.7 | 3.3 |
| mmw_first | 73.3 | 23.3 | 3.3 |
| age_19 | 60.0 | 16.7 | 23.3 |
| mmw_double | 43.3 | 16.7 | 40.0 |
| verbal_consent | 53.3 | 20.0 | 26.7 |
| warm_handoff_with_state | 66.7 | 6.7 | 26.7 |

### Compliance issues

- **transferred_without_consent**: 10 — e.g. "So we will let you onto the line with the license coordinator... All right. Here's the ringing on the line."
- **promise_will_get**: 4 — e.g. "my file shows you still have received your government subsidy allowance. I want to make sure you're still here in Florida to receive it."
- **no_recorded_line**: 3 — e.g. "(no company name or recorded-line disclosure given anywhere in the call)"
- **other**: 3 — e.g. "No mention that the call concerns their subsidy through the Affordable Care Act; only 'government subsidy allowance' was used."
- **guaranteed_outcome**: 2 — e.g. "The coordinator will go over the exact subsidy you may receive."
- **wrong_company_name**: 2 — e.g. "Okay. So thank you for calling Ema Marketing."

### Disqualifiers

- consumers with a disqualifier: 0
- caught by the agent: 0
- **transferred anyway: 0**
- types: {}