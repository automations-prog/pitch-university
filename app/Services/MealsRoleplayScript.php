<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * The Medicaid meals roleplay content, read from `ai-roleplay/meals/`: the
 * script (`script.md`), the objection library (`meals_objections.json`)
 * and the persona tables (`OBJECTIONS_AND_PERSONAS.md`). The delivery
 * criteria are shared with Medicare (`ai-roleplay/guide.md`).
 *
 * Parsed the ACA way, since the pack has the same shape. The differences:
 * the library weighs objections by the calls they lost, dispositions and
 * compliance paragraphs wrap over several lines, and there is no
 * forbidden-word list.
 *
 * @phpstan-import-type Disposition from RoleplayScript
 */
class MealsRoleplayScript extends AcaRoleplayScript
{
    protected const string DIRECTORY = 'ai-roleplay/meals';

    protected const string OBJECTIONS_DATA_FILE = 'meals_objections.json';

    protected const string SCRIPT_HEADING = '# Meals Script';

    protected const string REBUTTALS_HEADING = '# Meals Rebuttals';

    protected const string DQ_TRAPS_HEADING = '## Disqualifiers';

    protected const string OBJECTION_RISK_LABEL = 'Calls lost';

    protected const string OBJECTION_RISK_FIELD = 'calls_lost_pct';

    /**
     * Every Meals rebuttal ends "GO RIGHT BACK WHERE YOU WERE, DON'T STOP
     * OR PAUSE".
     */
    public const string REBUTTAL_CLOSER = "Acknowledge, answer briefly, then go right back where you were. Don't stop or pause.";

    /** The five boroughs, the only service area. */
    public const array BOROUGHS = ['Bronx', 'Brooklyn', 'Manhattan', 'Queens', 'Staten Island'];

    /** Plans that are Medicaid: naming one confirms it. */
    public const array QUALIFYING_PLANS = [
        'Healthfirst', 'Fidelis', 'MetroPlus', 'Emblem', 'VNS', 'Molina',
        'UHC Community', 'Anthem HealthPlus', 'Aetna Better Health', 'Amida Care',
    ];

    /** Companies that sell Medicaid and ordinary plans alike, so naming one settles nothing. */
    public const array PARENT_BRANDS = ['United', 'Aetna', 'Anthem', 'Cigna', 'Humana'];

    /**
     * The compliance section: each numbered step, then each paragraph
     * joined back into one line. Lead-in lines ("Required on every
     * call:") are dropped.
     *
     * @return list<string>
     */
    protected function parseComplianceGuidelines(string $script): array
    {
        $guidelines = [];

        foreach (preg_split('/\n\s*\n/', $this->section($script, '# Compliance Guidelines', static::SCRIPT_FILE)) ?: [] as $paragraph) {
            $rows = array_values(array_filter(array_map(trim(...), explode("\n", $paragraph)), fn (string $row): bool => $row !== '' && $row !== '---'));

            if ($rows === []) {
                continue;
            }

            if (str_ends_with($rows[0], ':')) {
                array_shift($rows);
            }

            if ($rows !== [] && preg_match('/^\d+\.\s/', $rows[0])) {
                foreach ($rows as $row) {
                    $guidelines[] = $this->clean((string) preg_replace('/^\d+\.\s*/', '', $row));
                }

                continue;
            }

            if ($rows !== []) {
                $guidelines[] = $this->clean(implode(' ', $rows));
            }
        }

        return $guidelines;
    }

    /**
     * `**Name** — description` rows, a description running on to the next
     * line until the next name.
     *
     * @return list<Disposition>
     */
    protected function parseDispositions(string $script): array
    {
        $dispositions = [];

        foreach (explode("\n", $this->section($script, '# Dispositions', static::SCRIPT_FILE)) as $row) {
            $row = trim($row);

            if (preg_match('/^\*\*(.+?)\*\*\s*[—–:-]\s*(.+)$/u', $row, $matches)) {
                $name = $this->clean($matches[1]);

                $dispositions[] = [
                    'id' => Str::slug($name, '_'),
                    'name' => $name,
                    'description' => $this->clean($matches[2]),
                ];

                continue;
            }

            if ($row !== '' && $row !== '---' && $dispositions !== []) {
                $dispositions[array_key_last($dispositions)]['description'] .= ' '.$this->clean($row);
            }
        }

        foreach (['transfer', 'hung_up_transfer', 'not_interested', 'dq', 'dnc'] as $required) {
            if (! in_array($required, array_column($dispositions, 'id'), true)) {
                throw $this->missing("disposition [{$required}]", static::SCRIPT_FILE);
            }
        }

        return $dispositions;
    }

    /**
     * The Meals script has no forbidden-word list: what may be said is the
     * approved wording, and the rest is caught by the instant fails.
     *
     * @return list<string>
     */
    protected function parseForbiddenWords(string $script): array
    {
        return [];
    }
}
