<?php

namespace App\Support;

class ReportDocument
{
    /**
     * @return array{
     *     empty: bool,
     *     farm: string,
     *     title: string,
     *     date: ?string,
     *     sections: list<array{title: string, items: list<array<string, mixed>>}>,
     *     footer: string
     * }
     */
    public static function parse(string $content): array
    {
        $farm = 'NB POULTRY FARM';
        $title = '';
        $date = null;
        $footer = '';
        $loose = [];
        $sections = [];
        $current = null;

        $flush = function () use (&$sections, &$current) {
            if ($current !== null) {
                $sections[] = $current;
                $current = null;
            }
        };

        $push = function (array $item) use (&$current, &$loose) {
            if ($current !== null) {
                $current['items'][] = $item;

                return;
            }

            $loose[] = $item;
        };

        foreach (preg_split("/\r\n|\n|\r/", $content) as $raw) {
            $line = trim($raw);

            if ($line === '' || preg_match('/^=+$/', $line)) {
                continue;
            }

            if (preg_match('/^-{5,}$/', $line)) {
                $push(['type' => 'divider']);
                continue;
            }

            if (preg_match('/^={3,}\s*(.+?)\s*={3,}$/', $line, $match)) {
                $heading = trim($match[1]);

                if ($title === '' && str_ends_with(strtoupper($heading), 'REPORT')) {
                    $title = self::prettyTitle($heading);
                    continue;
                }

                $flush();
                $current = [
                    'title' => self::prettyTitle($heading),
                    'items' => [],
                ];
                continue;
            }

            if (strcasecmp($line, 'NB POULTRY FARM') === 0) {
                $farm = $line;
                continue;
            }

            if (preg_match('/^Prepared by/i', $line)) {
                $footer = $line;
                continue;
            }

            if (preg_match('/^[A-Z][A-Z\s]{8,}$/', $line) && str_contains($line, 'REPORT') && $title === '') {
                $title = self::prettyTitle($line);
                continue;
            }

            if (preg_match('/^(.+?)\s+:\s+(.+)$/', $line, $match)) {
                $label = trim($match[1]);
                $value = trim($match[2]);

                if (strcasecmp($label, 'Report Date') === 0 && $date === null && $current === null && $sections === []) {
                    $date = $value;
                    continue;
                }

                $push([
                    'type' => 'kv',
                    'label' => $label,
                    'value' => $value,
                    'total' => (bool) preg_match('/^(TOTAL|REVENUE)\b/i', $label),
                    'alert' => str_contains(strtoupper($value), 'LOW STOCK'),
                ]);
                continue;
            }

            if (str_contains($line, ' | ')) {
                $parts = array_values(array_filter(array_map('trim', explode(' | ', $line)), fn ($part) => $part !== ''));
                $push([
                    'type' => 'record',
                    'parts' => $parts,
                ]);
                continue;
            }

            $push([
                'type' => 'note',
                'text' => $line,
            ]);
        }

        $flush();

        if ($loose !== []) {
            $sectionTitle = $title !== '' ? self::sectionFromTitle($title) : '';
            array_unshift($sections, [
                'title' => $sectionTitle,
                'items' => $loose,
            ]);
        }

        return [
            'empty' => $title === '' && $sections === [] && $date === null && $footer === '',
            'farm' => $farm,
            'title' => $title !== '' ? $title : 'Farm Report',
            'date' => $date,
            'sections' => $sections,
            'footer' => $footer,
        ];
    }

    public static function html(string $content): string
    {
        return view('components.report-document', ['content' => $content])->render();
    }

    public static function prettyTitle(string $value): string
    {
        return ucwords(strtolower(trim($value)));
    }

    public static function sectionFromTitle(string $title): string
    {
        return trim(preg_replace('/\s+Report$/i', '', $title) ?? $title);
    }
}
