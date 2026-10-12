<?php

namespace App\Services;

use Illuminate\Support\Str;
use JsonException;
use RuntimeException;

/**
 * The ACA roleplay content, read from `ai-roleplay/aca/`: the script
 * (`script.md`), the objection library (`aca_objections.json`) and the
 * persona tables (`OBJECTIONS_AND_PERSONAS.md`). The delivery criteria are
 * shared with Medicare (`ai-roleplay/guide.md`).
 *
 * No rebuttal wording lives in code: each objection names the script
 * heading its rebuttal is under, and the wording is pulled from the script,
 * so a script change reaches the consumer, the grader and the panels
 * without a deploy.
 *
 * @phpstan-import-type Rebuttal from RoleplayScript
 * @phpstan-import-type Objection from RoleplayScript
 * @phpstan-import-type Disposition from RoleplayScript
 */
class AcaRoleplayScript extends RoleplayScript
{
    protected const string SCRIPT_FILE = 'script.md';

    protected const string OBJECTIONS_FILE = 'OBJECTIONS_AND_PERSONAS.md';

    protected const string OBJECTIONS_DATA_FILE = 'aca_objections.json';

    protected const string SCRIPT_HEADING = '# ACA Script';

    protected const string REBUTTALS_HEADING = '# ACA Rebuttals';

    protected const string DQ_TRAPS_HEADING = '## The nine disqualifiers';

    protected const string OBJECTION_RISK_LABEL = 'Handled badly';

    /**
     * The script has no closing line over its rebuttals, so the panel shows
     * the loop pattern every ACA rebuttal follows.
     */
    public const string REBUTTAL_CLOSER = 'Acknowledge, answer briefly, then go straight back to the verification you were on.';

    /**
     * @return list<string>
     */
    protected function sourceFiles(): array
    {
        return [...parent::sourceFiles(), static::OBJECTIONS_DATA_FILE];
    }

    /**
     * The words the script forbids on a call, lowercased.
     *
     * @return list<string>
     */
    public function forbiddenWords(): array
    {
        return $this->content()['forbiddenWords'];
    }

    /**
     * Parse the docs without the cache.
     *
     * @return array<string, mixed>
     */
    public function parse(): array
    {
        $script = $this->read(static::SCRIPT_FILE);
        $personas = $this->read(static::OBJECTIONS_FILE);

        [$rebuttalCloser, $rebuttals] = $this->parseRebuttals($script);

        return [
            'deliveryCriteria' => $this->parseDeliveryCriteria($this->read(static::GUIDE_FILE)),
            'scriptSections' => $this->parseScriptSections($script),
            'rebuttalCloser' => $rebuttalCloser,
            'rebuttals' => $rebuttals,
            'complianceGuidelines' => $this->parseComplianceGuidelines($script),
            'dispositions' => $this->parseDispositions($script),
            'objections' => $this->parseObjectionLibrary($rebuttals),
            'levels' => $this->parseLevels($personas),
            'dqTraps' => $this->parseDqTraps($personas),
            'quirks' => $this->bullets($this->section($personas, '## Quirks', static::OBJECTIONS_FILE)),
            'quirkMinLevel' => self::QUIRK_MIN_LEVEL,
            'objectionRiskLabel' => static::OBJECTION_RISK_LABEL,
            'forbiddenWords' => $this->parseForbiddenWords($script),
        ];
    }

    /**
     * Every `**Heading**` under the rebuttals, with the lines below it.
     *
     * @return array{0: string, 1: list<Rebuttal>}
     */
    protected function parseRebuttals(string $script): array
    {
        $rebuttals = [];

        foreach (explode("\n", $this->section($script, static::REBUTTALS_HEADING, static::SCRIPT_FILE)) as $row) {
            $row = trim($row);

            if ($row === '' || $row === '---') {
                continue;
            }

            if (preg_match('/^\*\*(.+)\*\*$/', $row, $matches)) {
                $rebuttals[] = ['title' => $this->clean($matches[1]), 'lines' => []];

                continue;
            }

            if ($rebuttals !== []) {
                $rebuttals[array_key_last($rebuttals)]['lines'][] = $this->clean($row);
            }
        }

        if ($rebuttals === []) {
            throw $this->missing('rebuttals', static::SCRIPT_FILE);
        }

        return [self::REBUTTAL_CLOSER, $rebuttals];
    }

    /**
     * The compliance section, one guideline per line, with the numbering
     * and lead-in lines ("Required on every call:") dropped.
     *
     * @return list<string>
     */
    protected function parseComplianceGuidelines(string $script): array
    {
        $guidelines = [];

        foreach (explode("\n", $this->section($script, '# Compliance Guidelines', static::SCRIPT_FILE)) as $row) {
            $row = trim((string) preg_replace('/^\d+\.\s*/', '', trim($row)));

            if ($row === '' || $row === '---' || str_ends_with($row, ':')) {
                continue;
            }

            $guidelines[] = $this->clean($row);
        }

        return $guidelines;
    }

    /**
     * `**Name**: description` rows. "DQ (Disqualified)" is `dq` and
     * "Robo / Fake Recording" is `robo`.
     *
     * @return list<Disposition>
     */
    protected function parseDispositions(string $script): array
    {
        $dispositions = [];

        foreach (explode("\n", $this->section($script, '# Dispositions', static::SCRIPT_FILE)) as $row) {
            if (! preg_match('/^\*\*(.+?)\*\*:\s*(.+)$/u', trim($row), $matches)) {
                continue;
            }

            $name = $this->clean($matches[1]);
            $base = (string) preg_replace('/\s*(\(.*\)|\/.*)$/', '', $name);

            $dispositions[] = [
                'id' => Str::slug($base, '_'),
                'name' => $name,
                'description' => $this->clean($matches[2]),
            ];
        }

        foreach (['transfer', 'hung_up_transfer', 'not_interested', 'dq', 'dnc'] as $required) {
            if (! in_array($required, array_column($dispositions, 'id'), true)) {
                throw $this->missing("disposition [{$required}]", static::SCRIPT_FILE);
            }
        }

        return $dispositions;
    }

    /**
     * The objection library from `aca_objections.json`, each objection's
     * rebuttal pulled from the script by its `script_heading`.
     *
     * @param  list<Rebuttal>  $rebuttals
     * @return list<Objection>
     */
    protected function parseObjectionLibrary(array $rebuttals): array
    {
        try {
            $library = json_decode($this->read(static::OBJECTIONS_DATA_FILE), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Roleplay doc ['.static::OBJECTIONS_DATA_FILE.'] is not valid JSON.', previous: $exception);
        }

        $byTitle = array_column($rebuttals, null, 'title');
        $objections = [];

        foreach ($library['objections'] ?? [] as $objection) {
            $id = (string) ($objection['id'] ?? '');
            $heading = $this->clean((string) ($objection['script_heading'] ?? ''));

            if (! isset($byTitle[$heading])) {
                throw $this->missing("rebuttal heading [{$heading}] for objection [{$id}]", static::SCRIPT_FILE);
            }

            $consumerLines = array_values(array_map(
                fn (string $line): string => $this->clean($line),
                $objection['consumer_says'] ?? [],
            ));

            if ($consumerLines === []) {
                throw $this->missing("consumer lines for objection [{$id}]", static::OBJECTIONS_DATA_FILE);
            }

            $objections[] = [
                'id' => $id,
                'meaning' => $this->clean((string) $objection['label']),
                'seenPercent' => (float) $objection['seen_pct'],
                'lostPercent' => (float) $objection['badly_handled_pct'],
                'weight' => (int) $objection['draw_weight'],
                'minLevel' => (int) $objection['unlocks_at_level'],
                'consumerLines' => $consumerLines,
                'rebuttal' => $byTitle[$heading],
            ];
        }

        if ($objections === []) {
            throw $this->missing('objections', static::OBJECTIONS_DATA_FILE);
        }

        return $objections;
    }

    /**
     * The "Forbidden words — do not use these on a call: money, cash, …"
     * guideline as a word list.
     *
     * @return list<string>
     */
    protected function parseForbiddenWords(string $script): array
    {
        if (! preg_match('/^Forbidden words[^:]*:\s*(.+?)\.?$/mu', $this->section($script, '# Compliance Guidelines', static::SCRIPT_FILE), $matches)) {
            throw $this->missing('forbidden words', static::SCRIPT_FILE);
        }

        return array_values(array_filter(array_map(
            fn (string $word): string => strtolower(trim($word)),
            explode(',', $matches[1]),
        )));
    }

    /**
     * The ACA docs live in `ai-roleplay/aca/`; the delivery guide is the
     * shared one in `ai-roleplay/`.
     */
    protected function path(string $file): string
    {
        if ($this->directory !== null) {
            return $this->directory.'/'.$file;
        }

        return base_path($file === static::GUIDE_FILE ? 'ai-roleplay' : 'ai-roleplay/aca').'/'.$file;
    }
}
