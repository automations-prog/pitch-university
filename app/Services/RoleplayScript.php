<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Reads the roleplay content straight from `ai-roleplay/medicare_script.md`
 * and `ai-roleplay/OBJECTIONS_AND_PERSONAS.md`, so the docs stay the only
 * source of truth. Fails loudly when a heading or table it depends on is
 * renamed, instead of silently serving partial content.
 *
 * @phpstan-type ScriptSection array{id: string, title: string, lines: list<string>, branches: list<array{answer: string, response: string}>}
 * @phpstan-type Rebuttal array{title: string, lines: list<string>}
 * @phpstan-type Objection array{id: string, meaning: string, seenPercent: float, lostPercent: float, weight: int, minLevel: int, consumerLines: list<string>, rebuttal: Rebuttal}
 * @phpstan-type Level array{level: int, name: string, objectionRange: array{0: int, 1: int}, temperament: string, outcomeMix: array{transfer: int, dq: int, dnc: int}}
 * @phpstan-type DqTrap array{id: string, hiddenTruth: string}
 * @phpstan-type Disposition array{id: string, name: string, description: string}
 * @phpstan-type DeliveryCriterion array{key: string, label: string, description: string}
 * @phpstan-type Content array{deliveryCriteria: list<DeliveryCriterion>, scriptSections: list<ScriptSection>, rebuttalCloser: string, rebuttals: list<Rebuttal>, complianceGuidelines: list<string>, dispositions: list<Disposition>, objections: list<Objection>, levels: list<Level>, dqTraps: list<DqTrap>, quirks: list<string>, quirkMinLevel: int}
 */
class RoleplayScript
{
    /** The script section the trainee asks for the transfer in. */
    public const string TRANSFER_ASK_SECTION = 'ask_for_the_transfer';

    /** A quirk is attached to every persona at this level and up. */
    public const int QUIRK_MIN_LEVEL = 2;

    private const string SCRIPT_FILE = 'medicare_script.md';

    private const string OBJECTIONS_FILE = 'OBJECTIONS_AND_PERSONAS.md';

    private const string GUIDE_FILE = 'guide.md';

    /**
     * The `guide.md` criteria delivery scoring maps to a measurement or the
     * AI grader (see RoleplayDeliveryGrader).
     */
    public const array DELIVERY_CRITERIA = [
        'tonality', 'pace', 'strong_opener', 'filler_words', 'dead_air', 'listening',
        'quick_objection_handling', 'call_control', 'qualifying_accuracy', 'transfer_handoff', 'composure',
    ];

    /** @var Content|null */
    private ?array $content = null;

    public function __construct(private readonly ?string $directory = null) {}

    /**
     * All roleplay content, cached until either doc changes.
     *
     * @return Content
     */
    public function content(): array
    {
        return $this->content ??= Cache::rememberForever(
            'roleplay.script.'.md5(implode('|', array_map(
                fn (string $file): string => $this->path($file).filemtime($this->path($file)),
                [self::SCRIPT_FILE, self::OBJECTIONS_FILE, self::GUIDE_FILE],
            ))),
            fn (): array => $this->parse(),
        );
    }

    /**
     * @return Level
     */
    public function level(int $level): array
    {
        foreach ($this->content()['levels'] as $candidate) {
            if ($candidate['level'] === $level) {
                return $candidate;
            }
        }

        throw new RuntimeException("Roleplay level [{$level}] is not defined.");
    }

    /**
     * @return Objection|null
     */
    public function objection(string $id): ?array
    {
        foreach ($this->content()['objections'] as $objection) {
            if ($objection['id'] === $id) {
                return $objection;
            }
        }

        return null;
    }

    /**
     * @return DqTrap|null
     */
    public function dqTrap(string $id): ?array
    {
        foreach ($this->content()['dqTraps'] as $trap) {
            if ($trap['id'] === $id) {
                return $trap;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function dispositionIds(): array
    {
        return array_column($this->content()['dispositions'], 'id');
    }

    /**
     * Parse both docs without the cache.
     *
     * @return Content
     */
    public function parse(): array
    {
        $script = $this->read(self::SCRIPT_FILE);
        $personas = $this->read(self::OBJECTIONS_FILE);

        [$rebuttalCloser, $rebuttals] = $this->parseRebuttals($script);

        return [
            'deliveryCriteria' => $this->parseDeliveryCriteria($this->read(self::GUIDE_FILE)),
            'scriptSections' => $this->parseScriptSections($script),
            'rebuttalCloser' => $rebuttalCloser,
            'rebuttals' => $rebuttals,
            'complianceGuidelines' => $this->bullets($this->section($script, '# Compliance Guidelines', self::SCRIPT_FILE)),
            'dispositions' => $this->parseDispositions($script),
            'objections' => $this->parseObjections($personas, $rebuttals),
            'levels' => $this->parseLevels($personas),
            'dqTraps' => $this->parseDqTraps($personas),
            'quirks' => $this->bullets($this->section($personas, '## Quirks', self::OBJECTIONS_FILE)),
            'quirkMinLevel' => self::QUIRK_MIN_LEVEL,
        ];
    }

    /**
     * `guide.md` is one `Label: description` line per criterion.
     *
     * @return list<DeliveryCriterion>
     */
    private function parseDeliveryCriteria(string $guide): array
    {
        preg_match_all('/^([^:\n]+):\s*(.+)$/m', $guide, $rows, PREG_SET_ORDER);

        $criteria = array_map(fn (array $row): array => [
            'key' => Str::slug($this->clean($row[1]), '_'),
            'label' => $this->clean($row[1]),
            'description' => $this->clean($row[2]),
        ], $rows);

        foreach (self::DELIVERY_CRITERIA as $required) {
            if (! in_array($required, array_column($criteria, 'key'), true)) {
                throw $this->missing("criterion [{$required}]", self::GUIDE_FILE);
            }
        }

        return $criteria;
    }

    /**
     * @return list<ScriptSection>
     */
    private function parseScriptSections(string $script): array
    {
        $body = $this->section($script, '# Medicare Script', self::SCRIPT_FILE, prefix: true);
        $sections = [];

        foreach (preg_split('/^## /m', $body) ?: [] as $index => $chunk) {
            if ($index === 0) {
                continue;
            }

            $rows = explode("\n", trim($chunk));
            $title = $this->clean(array_shift($rows));
            $lines = [];
            $branches = [];

            foreach ($rows as $row) {
                $row = trim($row);

                if ($row === '' || $row === '---') {
                    continue;
                }

                if (str_starts_with($row, '- ')) {
                    [$answer, $response] = array_pad(explode(' - ', substr($row, 2), 2), 2, '');
                    $branches[] = ['answer' => $this->clean($answer), 'response' => $this->clean($response)];

                    continue;
                }

                $lines[] = $this->clean($row);
            }

            $sections[] = [
                'id' => Str::slug($title, '_'),
                'title' => $title,
                'lines' => $lines,
                'branches' => $branches,
            ];
        }

        $ids = array_column($sections, 'id');

        foreach (['opening', self::TRANSFER_ASK_SECTION] as $required) {
            if (! in_array($required, $ids, true)) {
                throw $this->missing("script section [{$required}]", self::SCRIPT_FILE);
            }
        }

        return $sections;
    }

    /**
     * @return array{0: string, 1: list<Rebuttal>}
     */
    private function parseRebuttals(string $script): array
    {
        $closer = null;
        $rebuttals = [];

        foreach (explode("\n", $this->section($script, '# Medicare Rebuttals', self::SCRIPT_FILE)) as $row) {
            $row = trim($row);

            if ($row === '' || $row === '---') {
                continue;
            }

            if (preg_match('/^\*\*(.+)\*\*$/', $row, $matches)) {
                $rebuttals[] = ['title' => $this->clean($matches[1]), 'lines' => []];

                continue;
            }

            if ($rebuttals === []) {
                $closer ??= $this->clean($row);

                continue;
            }

            $rebuttals[array_key_last($rebuttals)]['lines'][] = $this->clean($row);
        }

        if ($closer === null || $rebuttals === []) {
            throw $this->missing('rebuttals', self::SCRIPT_FILE);
        }

        return [$closer, $rebuttals];
    }

    /**
     * @return list<Disposition>
     */
    private function parseDispositions(string $script): array
    {
        $dispositions = [];

        foreach (explode("\n", $this->section($script, '## Dispositions', self::SCRIPT_FILE)) as $row) {
            if (preg_match('/^- \*\*(.+?)\*\*\s*[–-]\s*(.+)$/u', $row, $matches)) {
                $name = $this->clean($matches[1]);
                $dispositions[] = [
                    'id' => Str::slug(preg_replace('/\s*\(.*\)$/', '', $name) ?? $name, '_'),
                    'name' => $name,
                    'description' => $this->clean($matches[2]),
                ];

                continue;
            }

            if ($dispositions !== [] && preg_match('/^\s+- (.+)$/', $row, $matches)) {
                $dispositions[array_key_last($dispositions)]['description'] .= ' '.$this->clean($matches[1]);
            }
        }

        foreach (['transfer', 'dq', 'dnc'] as $required) {
            if (! in_array($required, array_column($dispositions, 'id'), true)) {
                throw $this->missing("disposition [{$required}]", self::SCRIPT_FILE);
            }
        }

        return $dispositions;
    }

    /**
     * @param  list<Rebuttal>  $rebuttals
     * @return list<Objection>
     */
    private function parseObjections(string $personas, array $rebuttals): array
    {
        $library = $this->section($personas, '# Medicare objection library', self::OBJECTIONS_FILE, prefix: true);
        $objections = [];

        preg_match_all(
            '/^\|\s*\d+\s*\|\s*`(\w+)`\s*\|\s*(.+?)\s*\|\s*([\d.]+)\s*\|\s*([\d.]+)\s*\|\s*(\d+)\s*\|\s*(\d)\s*\|$/m',
            $library,
            $rows,
            PREG_SET_ORDER,
        );

        foreach ($rows as [, $id, $meaning, $seen, $lost, $weight, $minLevel]) {
            $block = $this->objectionBlock($personas, $id);

            preg_match('/\*\*Consumer says[^*]*\*\*\s*\n((?:\s*- .+\n?)+)/u', $block, $saysMatch);
            preg_match('/\*\*Official rebuttal \(script\):\*\*\s*(.+)/u', $block, $rebuttalMatch);

            $consumerLines = array_map(
                fn (string $line): string => preg_replace('/^[“"]\s*|\s*[”"]$/u', '', $line) ?? $line,
                $this->bullets($saysMatch[1] ?? ''),
            );

            if ($consumerLines === [] || ! isset($rebuttalMatch[1])) {
                throw $this->missing("consumer lines or official rebuttal for objection [{$id}]", self::OBJECTIONS_FILE);
            }

            $objections[] = [
                'id' => $id,
                'meaning' => $this->clean($meaning),
                'seenPercent' => (float) $seen,
                'lostPercent' => (float) $lost,
                'weight' => (int) $weight,
                'minLevel' => (int) $minLevel,
                'consumerLines' => $consumerLines,
                'rebuttal' => $this->matchRebuttal($this->clean($rebuttalMatch[1]), $rebuttals),
            ];
        }

        if ($objections === []) {
            throw $this->missing('objection table', self::OBJECTIONS_FILE);
        }

        return $objections;
    }

    /**
     * The objection doc quotes each official rebuttal from the script, so
     * find the script rebuttal it came from. A line that isn't a rebuttal
     * (e.g. a script branch) is kept as a standalone script line.
     *
     * @param  list<Rebuttal>  $rebuttals
     * @return Rebuttal
     */
    private function matchRebuttal(string $line, array $rebuttals): array
    {
        foreach ($rebuttals as $rebuttal) {
            if (in_array($line, $rebuttal['lines'], true)) {
                return $rebuttal;
            }
        }

        return ['title' => 'Script line', 'lines' => [$line]];
    }

    private function objectionBlock(string $personas, string $id): string
    {
        if (! preg_match('/^### `'.preg_quote($id, '/').'`.*?(?=^### |^---|\z)/ms', $personas, $matches)) {
            throw $this->missing("### `{$id}` section", self::OBJECTIONS_FILE);
        }

        return $matches[0];
    }

    /**
     * @return list<Level>
     */
    private function parseLevels(string $personas): array
    {
        preg_match_all(
            '/^\|\s*(\d)\s*\|\s*([^|]+?)\s*\|\s*(\d)\s*[–-]\s*(\d)\s*\|\s*(.+?)\s*\|$/mu',
            $this->section($personas, '## Difficulty levels', self::OBJECTIONS_FILE),
            $rows,
            PREG_SET_ORDER,
        );

        $mix = $this->parseOutcomeMix($personas);
        $levels = [];

        foreach ($rows as [, $level, $name, $min, $max, $temperament]) {
            $level = (int) $level;

            if (! isset($mix[$level])) {
                throw $this->missing("outcome mix for level [{$level}]", self::OBJECTIONS_FILE);
            }

            $levels[] = [
                'level' => $level,
                'name' => $this->clean($name),
                'objectionRange' => [(int) $min, (int) $max],
                'temperament' => $this->clean($temperament),
                'outcomeMix' => $mix[$level],
            ];
        }

        if (array_column($levels, 'level') !== [1, 2, 3, 4, 5]) {
            throw $this->missing('levels 1–5 in the difficulty table', self::OBJECTIONS_FILE);
        }

        return $levels;
    }

    /**
     * @return array<int, array{transfer: int, dq: int, dnc: int}>
     */
    private function parseOutcomeMix(string $personas): array
    {
        preg_match_all(
            '/^\|\s*(\d)(?:\s*[–-]\s*(\d))?\s*\|\s*(\S+)\s*\|\s*(\S+)\s*\|\s*(\S+)\s*\|$/mu',
            $this->section($personas, '## Outcome mix by level', self::OBJECTIONS_FILE),
            $rows,
            PREG_SET_ORDER,
        );

        $percent = fn (string $cell): int => (int) rtrim($cell, '%');
        $mix = [];

        foreach ($rows as [, $from, $to, $transfer, $dq, $dnc]) {
            foreach (range((int) $from, $to === '' ? (int) $from : (int) $to) as $level) {
                $mix[$level] = [
                    'transfer' => $percent($transfer),
                    'dq' => $percent($dq),
                    'dnc' => $percent($dnc),
                ];
            }
        }

        return $mix;
    }

    /**
     * @return list<DqTrap>
     */
    private function parseDqTraps(string $personas): array
    {
        preg_match_all(
            '/^\|\s*`(\w+)`\s*\|\s*(.+?)\s*\|$/m',
            $this->section($personas, '## Disqualifier traps', self::OBJECTIONS_FILE),
            $rows,
            PREG_SET_ORDER,
        );

        if ($rows === []) {
            throw $this->missing('DQ trap table', self::OBJECTIONS_FILE);
        }

        return array_map(fn (array $row): array => [
            'id' => $row[1],
            'hiddenTruth' => $this->clean($row[2]),
        ], $rows);
    }

    /**
     * The body under a heading, up to the next heading of the same or a
     * higher level.
     */
    private function section(string $markdown, string $heading, string $file, bool $prefix = false): string
    {
        $hashes = strspn($heading, '#');
        $title = preg_quote($heading, '/').($prefix ? '[^\n]*' : '');
        $pattern = '/^'.$title.'\s*\n(.*?)(?=^#{1,'.$hashes.'} |\z)/ms';

        if (! preg_match($pattern, $markdown, $matches)) {
            throw $this->missing("heading [{$heading}]", $file);
        }

        return $matches[1];
    }

    /**
     * Top-level `- ` bullets under a block.
     *
     * @return list<string>
     */
    private function bullets(string $block): array
    {
        preg_match_all('/^\s*- (.+)$/m', $block, $matches);

        return array_map($this->clean(...), $matches[1]);
    }

    /**
     * Strip markdown emphasis and escapes, and straighten curly single
     * quotes, so the text reads like the script.
     */
    private function clean(string $text): string
    {
        return trim(str_replace(['**', '\\_', '\\*', '‘', '’'], ['', '_', '*', "'", "'"], $text));
    }

    private function read(string $file): string
    {
        $path = $this->path($file);

        if (! is_file($path)) {
            throw new RuntimeException("Roleplay doc [{$file}] is missing.");
        }

        return str_replace("\r\n", "\n", (string) file_get_contents($path));
    }

    private function path(string $file): string
    {
        return ($this->directory ?? base_path('ai-roleplay')).'/'.$file;
    }

    private function missing(string $what, string $file): RuntimeException
    {
        return new RuntimeException("Roleplay parser could not find the {$what} in {$file}. Was a heading renamed?");
    }
}
