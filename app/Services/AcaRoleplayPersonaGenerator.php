<?php

namespace App\Services;

use App\Enums\Outcome;
use App\Enums\RealtimeVoice;
use Random\Randomizer;

/**
 * Builds an ACA consumer following `ai-roleplay/aca/OBJECTIONS_AND_PERSONAS.md`.
 * On top of the shared draw (objections, quirk, outcome, DQ trap) every
 * persona gets an age and the hidden coverage facts the agent has to
 * uncover: a Marketplace plan (not a disqualifier), a named carrier that
 * needs the "through the Marketplace?" check, not knowing what they have,
 * and SSI (not a disqualifier, unlike SSDI).
 *
 * @phpstan-type Coverage array{has_marketplace_plan: bool, carrier: string|null, knows_what_they_have: bool, receives_ssi: bool}
 */
class AcaRoleplayPersonaGenerator extends RoleplayPersonaGenerator
{
    /**
     * Working-age dialer leads. Each keeps one voice of their gender, so a
     * name always sounds the same.
     *
     * @var list<array{name: string, state: string, zip: string, gender: 'male'|'female', voice: string}>
     */
    private const array LEADS = [
        ['name' => 'Ashley Turner', 'state' => 'Texas', 'zip' => '75217', 'gender' => 'female', 'voice' => 'marin'],
        ['name' => 'Marcus Johnson', 'state' => 'Georgia', 'zip' => '30906', 'gender' => 'male', 'voice' => 'cedar'],
        ['name' => 'Jessica Ramirez', 'state' => 'Florida', 'zip' => '33511', 'gender' => 'female', 'voice' => 'marin'],
        ['name' => 'Tyler Brooks', 'state' => 'Ohio', 'zip' => '43204', 'gender' => 'male', 'voice' => 'cedar'],
        ['name' => 'Brittany Lewis', 'state' => 'North Carolina', 'zip' => '27406', 'gender' => 'female', 'voice' => 'marin'],
        ['name' => 'Kevin Murphy', 'state' => 'Tennessee', 'zip' => '37211', 'gender' => 'male', 'voice' => 'cedar'],
        ['name' => 'Danielle Price', 'state' => 'Arizona', 'zip' => '85308', 'gender' => 'female', 'voice' => 'marin'],
        ['name' => 'Jordan Mitchell', 'state' => 'Louisiana', 'zip' => '70805', 'gender' => 'male', 'voice' => 'cedar'],
        ['name' => 'Crystal Howard', 'state' => 'Oklahoma', 'zip' => '73107', 'gender' => 'female', 'voice' => 'marin'],
        ['name' => 'Anthony Reed', 'state' => 'South Carolina', 'zip' => '29203', 'gender' => 'male', 'voice' => 'cedar'],
    ];

    /** Carriers that sell Marketplace plans and others, so naming one settles nothing. */
    public const array CARRIERS = ['Blue Cross', 'Oscar', 'Ambetter', 'Anthem', 'UnitedHealthcare'];

    /** About a third of qualified consumers already have a Marketplace plan. */
    private const int MARKETPLACE_PERCENT = 33;

    /** How many Marketplace holders name their carrier instead of "Marketplace". */
    private const int NAMES_CARRIER_PERCENT = 50;

    /** 10–25% genuinely can't say what coverage they have. */
    private const int DOESNT_KNOW_PERCENT = 20;

    private const int SSI_PERCENT = 10;

    public function __construct(AcaRoleplayScript $script, ?Randomizer $randomizer = null)
    {
        parent::__construct($script, $randomizer);
    }

    /**
     * @return list<array{name: string, state: string, zip: string, gender: 'male'|'female', voice: string}>
     */
    protected function leads(): array
    {
        return self::LEADS;
    }

    /** States a "wrong state" lead actually lives in. */
    private const array OTHER_STATES = ['Arkansas', 'Louisiana', 'Illinois', 'Oklahoma', 'Alabama', 'Mississippi', 'Kentucky', 'Indiana'];

    /**
     * Objections that only make sense with a Marketplace plan, and the one
     * where the consumer names a carrier.
     */
    private const array MARKETPLACE_OBJECTIONS = ['already_have_marketplace', 'insurance_not_familiar'];

    /**
     * @param  array<string, mixed>|null  $scenario  a training module's roleplay, see the parent
     * @return array{outcome: Outcome, voice: RealtimeVoice, persona: array<string, mixed>}
     */
    public function generate(int $level, ?array $scenario = null): array
    {
        $generated = parent::generate($level, $scenario);
        $persona = $generated['persona'];
        $trapId = $persona['dq_trap']['id'] ?? null;

        // A Marketplace objection from someone with a disqualifier would
        // have them lie about their coverage. A private-plan holder may
        // still name their carrier.
        $persona['objections'] = array_values(array_filter(
            $persona['objections'],
            fn (string $id): bool => $trapId === null
                || ! in_array($id, self::MARKETPLACE_OBJECTIONS, true)
                || ($trapId === 'private' && $id === 'insurance_not_familiar'),
        ));

        if ($level >= (int) config('roleplay.aca.focus_guaranteed_from_level')) {
            $persona['objections'] = $this->withFocusObjection($persona['objections'], $level);
        }

        // Medicare DQs are 66–78 by design: age is a tell the agent should hear.
        $persona['age'] = $trapId === 'medicare'
            ? $this->randomizer->getInt(66, 78)
            : $this->randomizer->getInt(19, 64);
        $persona['coverage'] = $this->coverage($trapId, $persona['objections']);

        // The dialer has the wrong state; the agent has to update it.
        if (in_array('wrong_state', $persona['objections'], true)) {
            $persona['actual_state'] = $this->pick(array_values(array_diff(self::OTHER_STATES, [$persona['lead']['state']])));
        }

        $generated['persona'] = $persona;

        return $generated;
    }

    /**
     * Focus objections are drawn more often, and brush-offs more often at
     * the hardest levels.
     *
     * @param  array{id: string, weight: int}  $objection
     */
    protected function objectionWeight(array $objection, int $level): int
    {
        $weight = $objection['weight'];

        if (in_array($objection['id'], config('roleplay.aca.focus_objections'), true)) {
            $weight *= (int) config('roleplay.aca.focus_weight_multiplier');
        }

        if ($level >= (int) config('roleplay.aca.brush_off_from_level')
            && in_array($objection['id'], config('roleplay.aca.brush_off_objections'), true)) {
            $weight *= (int) config('roleplay.aca.brush_off_weight_multiplier');
        }

        return $weight;
    }

    /**
     * Swaps the last drawn objection for a focus one, by weight, when none
     * was drawn.
     *
     * @param  list<string>  $objections
     * @return list<string>
     */
    private function withFocusObjection(array $objections, int $level): array
    {
        $focusIds = config('roleplay.aca.focus_objections');

        if (array_intersect($objections, $focusIds) !== []) {
            return $objections;
        }

        $candidates = array_values(array_filter(
            $this->script->content()['objections'],
            fn (array $objection): bool => in_array($objection['id'], $focusIds, true) && $objection['minLevel'] <= $level,
        ));

        if ($candidates === []) {
            return $objections;
        }

        $focus = $this->weightedSample($candidates, 1, fn (array $objection): int => $this->objectionWeight($objection, $level))[0];

        if ($objections !== []) {
            array_pop($objections);
        }

        return [...$objections, $focus['id']];
    }

    /**
     * @param  list<string>  $objections
     * @return Coverage
     */
    private function coverage(?string $trapId, array $objections): array
    {
        // A private plan bought straight from a carrier sounds just like a
        // Marketplace one until the agent asks.
        if ($trapId === 'private') {
            return [
                'has_marketplace_plan' => false,
                'carrier' => $this->pick(self::CARRIERS),
                'knows_what_they_have' => true,
                'receives_ssi' => false,
            ];
        }

        $namesCarrier = in_array('insurance_not_familiar', $objections, true);
        $hasMarketplacePlan = $trapId === null
            && ($namesCarrier
                || in_array('already_have_marketplace', $objections, true)
                // Not wanting to change their insurance means they have some.
                || in_array('dont_want_change', $objections, true)
                || $this->chance(self::MARKETPLACE_PERCENT));

        return [
            'has_marketplace_plan' => $hasMarketplacePlan,
            'carrier' => $hasMarketplacePlan && ($namesCarrier || $this->chance(self::NAMES_CARRIER_PERCENT)) ? $this->pick(self::CARRIERS) : null,
            'knows_what_they_have' => ! in_array('dont_know_what_i_have', $objections, true) && ! $this->chance(self::DOESNT_KNOW_PERCENT),
            'receives_ssi' => $trapId === null && $this->chance(self::SSI_PERCENT),
        ];
    }

    private function chance(int $percent): bool
    {
        return $this->randomizer->getInt(1, 100) <= $percent;
    }
}
