<?php

namespace App\Http\Resources;

use App\Models\RoleplaySession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RoleplaySession
 */
class RoleplaySessionResource extends JsonResource
{
    /**
     * Transform the resource into an array. The persona's hidden parts
     * (outcome, objections, DQ trap) are only included once the call has
     * ended.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isEnded = $this->ended_at !== null;

        return [
            'id' => $this->id,
            'vertical' => $this->vertical,
            'level' => $this->level,
            'lead' => $this->persona['lead'],
            'created_at' => $this->created_at,
            'ended_at' => $this->ended_at,
            'disposition' => $this->disposition,
            'passed' => $this->passed,
            $this->mergeWhen($isEnded, fn (): array => [
                'expected_outcome' => $this->expected_outcome,
                // What the consumer did can change the right code (a
                // second busy, a DNC request); older calls only have the
                // persona's outcome.
                'correct_disposition' => $this->score['correct_disposition'] ?? $this->expected_outcome->value,
                'correct_reason' => $this->score['correct_reason'] ?? null,
                'end_reason' => $this->end_reason,
                'checks' => $this->score['checks'] ?? [],
                'delivery_status' => $this->delivery_status,
                'delivery' => $this->delivery,
                'dq_trap' => $this->persona['dq_trap'],
                'hidden_facts' => $this->hiddenFacts(),
                'objections' => array_values(array_filter(array_map(
                    fn (string $id): ?array => $this->vertical->script()->objection($id),
                    $this->persona['objections'],
                ))),
                'transcript' => $this->transcript,
                'recording_url' => $this->recording_path === null
                    ? null
                    : route($this->vertical->routePrefix().'.sessions.recording', $this->resource),
            ]),
        ];
    }

    /**
     * The persona's hidden facts the trainee had to uncover (ACA coverage,
     * the Meals Medicaid answer and borough), in words, for the reveal
     * after the call.
     *
     * @return list<string>
     */
    private function hiddenFacts(): array
    {
        $persona = $this->persona;
        $coverage = $persona['coverage'] ?? null;
        $facts = [];

        if (isset($persona['age'])) {
            $facts[] = "{$persona['age']} years old.";
        }

        if (isset($persona['actual_state'])) {
            $facts[] = "Actually lives in {$persona['actual_state']}, not {$persona['lead']['state']}.";
        }

        if (isset($persona['medicaid'])) {
            return [...$facts, ...$this->medicaidFacts()];
        }

        if ($coverage === null) {
            return $facts;
        }

        if ($coverage['has_marketplace_plan']) {
            $facts[] = $coverage['carrier'] === null
                ? 'Has a Marketplace plan (not a disqualifier).'
                : "Has a {$coverage['carrier']} plan through the Marketplace (not a disqualifier, once confirmed it's Marketplace).";
        } elseif ($persona['dq_trap'] === null) {
            $facts[] = 'Has no health insurance.';
        }

        if (! $coverage['knows_what_they_have']) {
            $facts[] = "Wasn't sure what coverage they had.";
        }

        if ($coverage['receives_ssi']) {
            $facts[] = 'Receives SSI (not a disqualifier, unlike SSDI).';
        }

        return $facts;
    }

    /**
     * The Meals persona's Medicaid answer and borough, in words.
     *
     * @return list<string>
     */
    private function medicaidFacts(): array
    {
        $persona = $this->persona;
        $medicaid = $persona['medicaid'];
        $saysBorough = $persona['says_borough'] ?? null;

        $facts = [match ($medicaid['status']) {
            'yes' => 'Has Medicaid.',
            'no' => 'Has no Medicaid.',
            'medicare_only' => 'Has Medicare, not Medicaid.',
            'names_plan' => $persona['dq_trap'] === null
                ? "Named their plan, {$medicaid['plan']}, which is Medicaid."
                : "Named {$medicaid['plan']}, a parent brand that settles nothing until you ask which plan.",
            'dont_know' => $medicaid['pays_copays']
                ? "Didn't know if they had Medicaid, and pays copays at the doctor (so not Medicaid)."
                : "Didn't know if they had Medicaid, but the doctor is free (so Medicaid).",
        }];

        if ($saysBorough === 'Long Island City') {
            $facts[] = 'Said Long Island City, which is in Queens and qualifies.';
        } elseif ($saysBorough === 'New York') {
            $facts[] = "Said just \"New York\": the borough had to be pinned down ({$persona['lead']['borough']}).";
        } elseif ($saysBorough !== null) {
            $facts[] = "Lives in {$saysBorough}, outside the five boroughs.";
        }

        return $facts;
    }
}
