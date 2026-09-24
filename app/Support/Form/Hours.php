<?php

namespace App\Support\Form;

/**
 * Converts opening hours between the API shape and the x-form.weekly-hours value.
 *   API:      {weekly: {mon: [{open, close}]}, exceptions: [{date, closed, note?, windows?: [{open, close}]}]}
 *   Control:  {weekly: {mon: {open: bool, intervals: [{from, to}]}}, exceptions: [{date, label, open, from, to}]}
 */
final class Hours
{
    public const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    /** @param  array<string, mixed>|null  $api */
    public static function toControl(?array $api): array
    {
        $weekly = [];
        foreach (self::DAYS as $d) {
            $windows = array_values((array) ($api['weekly'][$d] ?? []));
            $weekly[$d] = ['open' => $windows !== [], 'intervals' => array_map(fn ($w) => ['from' => $w['open'] ?? null, 'to' => $w['close'] ?? null], $windows)];
        }
        $ex = [];
        foreach ((array) ($api['exceptions'] ?? []) as $e) {
            $w = $e['windows'][0] ?? null;
            $ex[] = ['date' => $e['date'] ?? null, 'label' => $e['note'] ?? '', 'open' => empty($e['closed']) && $w !== null, 'from' => $w['open'] ?? null, 'to' => $w['close'] ?? null];
        }

        return ['weekly' => $weekly, 'exceptions' => $ex];
    }

    /** @param  array<string, mixed>|string|null  $control */
    public static function toApi(array|string|null $control): array
    {
        $control = is_string($control) ? (array) json_decode($control, true) : (array) $control;
        $weekly = [];
        foreach (self::DAYS as $d) {
            $day = $control['weekly'][$d] ?? [];
            $weekly[$d] = empty($day['open']) ? [] : array_values(array_map(fn ($i) => ['open' => $i['from'], 'close' => $i['to']], array_filter((array) ($day['intervals'] ?? []), fn ($i) => ! empty($i['from']) && ! empty($i['to']))));
        }
        $ex = [];
        foreach ((array) ($control['exceptions'] ?? []) as $e) {
            if (empty($e['date'])) {
                continue;
            }
            $row = ['date' => $e['date'], 'closed' => empty($e['open'])];
            if (! $row['closed'] && ! empty($e['from']) && ! empty($e['to'])) {
                $row['windows'] = [['open' => $e['from'], 'close' => $e['to']]];
            }
            if (! empty($e['label'])) {
                $row['note'] = $e['label'];
            }
            $ex[] = $row;
        }

        return ['weekly' => $weekly, 'exceptions' => $ex];
    }
}
