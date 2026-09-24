<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * The date range the top-bar picker controls (query string: ?range=7d or ?from=YYYY-MM-DD&to=YYYY-MM-DD).
 * Days are property (Lagos) calendar days. `previous` is the equally long period right before, for "% vs previous".
 */
final class DateRange
{
    public const PRESETS = ['today' => 'Today', 'yesterday' => 'Yesterday', '7d' => 'Last 7 days', '30d' => 'Last 30 days', 'month' => 'This month', 'custom' => 'Custom range'];

    public function __construct(
        public readonly string $from,
        public readonly string $to,
        public readonly string $preset,
    ) {}

    public static function fromRequest(?Request $request = null, string $default = '7d'): self
    {
        $request ??= request();
        $today = CarbonImmutable::parse(Time::today(), self::tz());
        $from = self::day($request->query('from'));
        $to = self::day($request->query('to'));
        if ($from !== null && $to !== null) {
            if ($from > $to) {
                [$from, $to] = [$to, $from];
            }

            return new self($from, min($to, $today->toDateString()) ?: $to, 'custom');
        }
        $preset = (string) $request->query('range', $default);

        return self::preset(array_key_exists($preset, self::PRESETS) && $preset !== 'custom' ? $preset : $default, $today);
    }

    public static function preset(string $preset, ?CarbonImmutable $today = null): self
    {
        $today ??= CarbonImmutable::parse(Time::today(), self::tz());

        return match ($preset) {
            'today' => new self($today->toDateString(), $today->toDateString(), 'today'),
            'yesterday' => new self($today->subDay()->toDateString(), $today->subDay()->toDateString(), 'yesterday'),
            '30d' => new self($today->subDays(29)->toDateString(), $today->toDateString(), '30d'),
            'month' => new self($today->startOfMonth()->toDateString(), $today->toDateString(), 'month'),
            default => new self($today->subDays(6)->toDateString(), $today->toDateString(), '7d'),
        };
    }

    public function days(): int
    {
        return (int) CarbonImmutable::parse($this->from)->diffInDays(CarbonImmutable::parse($this->to)) + 1;
    }

    /** The equally long period immediately before this one. */
    public function previous(): self
    {
        $n = $this->days();
        $from = CarbonImmutable::parse($this->from)->subDays($n);
        $to = CarbonImmutable::parse($this->from)->subDay();

        return new self($from->toDateString(), $to->toDateString(), 'previous');
    }

    /** @return list<string> every day in the range, oldest first */
    public function each(): array
    {
        $out = [];
        for ($d = CarbonImmutable::parse($this->from); $d->toDateString() <= $this->to; $d = $d->addDay()) {
            $out[] = $d->toDateString();
        }

        return $out;
    }

    public function label(): string
    {
        $f = CarbonImmutable::parse($this->from);
        $t = CarbonImmutable::parse($this->to);

        return $this->from === $this->to ? $f->format('M j, Y') : $f->format('M j, Y').' - '.$t->format('M j, Y');
    }

    private static function day(mixed $v): ?string
    {
        return is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
    }

    private static function tz(): string
    {
        return (string) config('r007.display_timezone', 'Africa/Lagos');
    }
}
