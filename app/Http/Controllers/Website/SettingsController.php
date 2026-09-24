<?php

namespace App\Http\Controllers\Website;

use App\Services\R007Api\R007ApiException;
use App\Support\Cms\Cms;
use App\Support\Cms\SettingsGroups;
use Illuminate\Http\Request;

/**
 * Site settings: eight groups on eight tabs, one Save. Each changed group is one `PUT /settings/{group}` with that group's rowVersion
 * (If-Match); unchanged groups are not sent. A group the API refuses keeps its edits on screen with the errors on its own tab.
 */
class SettingsController extends WebsiteController
{
    public function edit()
    {
        $res = $this->cms->fetch('settings');
        $groups = (array) ($res->data['groups'] ?? []);
        $media = (array) ($res->data['media'] ?? []);
        $values = [];
        $versions = [];
        foreach (SettingsGroups::all() as $g => $def) {
            $values[$g] = (array) ($groups[$g]['value'] ?? []);
            $versions[$g] = $groups[$g]['rowVersion'] ?? null;
        }

        return view('pages.website.settings', [
            'defs' => SettingsGroups::all(), 'loaded' => $res, 'values' => $values, 'versions' => $versions, 'media' => $media, 'canManage' => $this->can(Cms::MANAGE),
            'hours' => $this->hoursModel($values['hours'] ?? []),
        ]);
    }

    public function update(Request $request)
    {
        $current = (array) ($this->cms->get('settings')['groups'] ?? []);
        $saved = [];
        $failed = [];
        $errors = [];
        foreach (array_keys(SettingsGroups::all()) as $g) {
            if (! $request->has($g) && $g !== 'hours') {
                continue;
            }
            $value = $this->build($g, $request);
            $before = (array) ($current[$g]['value'] ?? []);
            if ($this->same($before, $value)) {
                continue;
            }
            try {
                $this->cms->put("settings/{$g}", ['value' => $value], $request->input("versions.{$g}", $current[$g]['rowVersion'] ?? null));
                $saved[] = SettingsGroups::all()[$g]['label'];
            } catch (R007ApiException $e) {
                if ($e->isUnauthenticated() || $e->isUnreachable()) {
                    throw $e;
                }
                $failed[] = SettingsGroups::all()[$g]['label'].': '.($e->errors() ? 'please correct the highlighted fields' : ($e->detail ?: $e->title));
                foreach ($e->errors() as $key => $messages) {
                    $errors[$g.'.'.preg_replace('/^value\./', '', (string) $key)] = $messages;
                }
            }
        }
        if ($failed !== []) {
            $back = back()->withInput()->with('error', 'Some settings were not saved. '.implode(' ', $failed))->withErrors($errors);

            return $saved ? $back->with('success', 'Saved: '.implode(', ', $saved).'.') : $back;
        }

        return redirect()->route('website.settings')->with('success', $saved ? 'Saved: '.implode(', ', $saved).'. The website shows the changes right away.' : 'Nothing had changed, so nothing was saved.');
    }

    // ------------------------------------------------------------------

    /**
     * Form input -> the group's API value.
     *
     * @return array<string, mixed>
     */
    private function build(string $g, Request $request): array
    {
        $in = (array) $request->input($g, []);
        $str = fn ($k) => ($v = trim((string) ($in[$k] ?? ''))) === '' ? null : $v;
        if ($g === 'hours') {
            $weekly = [];
            foreach (array_keys(SettingsGroups::DAYS) as $d) {
                $row = (array) ($in['weekly'][$d] ?? []);
                $closed = filter_var($row['closed'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $weekly[] = ['day' => $d, 'open' => $closed ? null : ($this->time($row['open'] ?? null) ?? '08:00'), 'close' => $closed ? null : ($this->time($row['close'] ?? null) ?? '22:00'), 'closed' => $closed];
            }
            $holidays = [];
            foreach ((array) ($in['holidays'] ?? []) as $h) {
                $date = trim((string) ($h['date'] ?? ''));
                if ($date === '') {
                    continue;
                }
                $closed = filter_var($h['closed'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $holidays[] = ['date' => $date, 'label' => trim((string) ($h['label'] ?? '')), 'open' => $closed ? null : $this->time($h['open'] ?? null), 'close' => $closed ? null : $this->time($h['close'] ?? null), 'closed' => $closed];
            }

            return ['weekly' => $weekly, 'notes' => $str('notes'), 'holidays' => $holidays];
        }
        $out = [];
        foreach (SettingsGroups::all()[$g]['fields'] as $f) {
            $k = $f['key'];
            $out[$k] = match ($f['type']) {
                'toggle' => filter_var($in[$k] ?? false, FILTER_VALIDATE_BOOLEAN),
                'decimal' => is_numeric($in[$k] ?? null) ? (float) $in[$k] : null,
                default => $str($k),
            };
        }

        return $out;
    }

    private function time(mixed $v): ?string
    {
        $v = trim((string) $v);

        return preg_match('/^\d{1,2}:\d{2}/', $v) ? substr(str_pad($v, 5, '0', STR_PAD_LEFT), 0, 5) : null;
    }

    /** Equal ignoring key order and null-versus-empty-string. */
    private function same(array $a, array $b): bool
    {
        $norm = function ($v) use (&$norm) {
            if (is_array($v)) {
                if (! array_is_list($v)) {
                    ksort($v);
                }

                return array_map($norm, $v);
            }

            return $v === '' ? null : (is_numeric($v) && ! is_string($v) ? (float) $v : $v);
        };

        return $norm($a) === $norm($b);
    }

    /**
     * The opening-hours editor's starting state: every weekday present, whatever the API sent.
     *
     * @param  array<string, mixed>  $v
     * @return array{weekly: array<string, array<string, mixed>>, holidays: list<array<string, mixed>>}
     */
    private function hoursModel(array $v): array
    {
        $byDay = [];
        foreach ((array) ($v['weekly'] ?? []) as $row) {
            if (is_array($row) && isset($row['day'])) {
                $byDay[strtoupper((string) $row['day'])] = $row;
            }
        }
        $weekly = [];
        foreach (array_keys(SettingsGroups::DAYS) as $d) {
            $r = $byDay[$d] ?? [];
            $weekly[$d] = ['open' => (string) ($r['open'] ?? '08:00'), 'close' => (string) ($r['close'] ?? '22:00'), 'closed' => (bool) ($r['closed'] ?? false)];
        }
        $holidays = array_values(array_map(fn ($h) => ['date' => (string) ($h['date'] ?? ''), 'label' => (string) ($h['label'] ?? ''), 'open' => (string) ($h['open'] ?? '10:00'), 'close' => (string) ($h['close'] ?? '18:00'), 'closed' => (bool) ($h['closed'] ?? false)], array_filter((array) ($v['holidays'] ?? []), 'is_array')));

        return ['weekly' => $weekly, 'holidays' => $holidays];
    }
}
