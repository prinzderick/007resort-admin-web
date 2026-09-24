<?php

namespace App\Support\Cms;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Small, defensive helpers shared by every Website (CMS) screen: Lagos <-> UTC, status words, media accessors, the public-site link.
 * The API sends UTC ISO-8601; editors think in Lagos wall-clock time. Every accessor tolerates a missing or oddly shaped field
 * (the lesson from earlier screens breaking on real field names).
 */
final class Cms
{
    public const TZ = 'Africa/Lagos';

    /** Permission codes (docs/CMS_API.md section 5). */
    public const VIEW = 'cms.view';

    public const MANAGE = 'cms.manage';

    public const PUBLISH = 'cms.publish';

    public const MEDIA = 'cms.media.manage';

    public const SUBSCRIBERS = 'cms.subscribers.view';

    public const EXPORT = 'cms.subscribers.export';

    public const MESSAGES = 'cms.messages.manage';

    public static function parse(?string $utc): ?CarbonImmutable
    {
        if ($utc === null || $utc === '') {
            return null;
        }
        try {
            return CarbonImmutable::parse($utc)->setTimezone(self::TZ);
        } catch (Throwable) {
            return null;
        }
    }

    /** "2026-10-09T17:00:00.000Z" -> "2026-10-09T18:00" (the value of a Lagos date+time input). */
    public static function toLocalInput(?string $utc): string
    {
        return self::parse($utc)?->format('Y-m-d\TH:i') ?? '';
    }

    /** "2026-10-09T18:00" (Lagos wall clock) -> "2026-10-09T17:00:00.000Z". Null when blank or unparseable. */
    public static function fromLocalInput(?string $local): ?string
    {
        $local = trim((string) $local);
        if ($local === '') {
            return null;
        }
        try {
            return CarbonImmutable::parse($local, self::TZ)->utc()->format('Y-m-d\TH:i:s.v\Z');
        } catch (Throwable) {
            return null;
        }
    }

    /** "12 Oct 2026, 6:00 PM" in Lagos time. */
    public static function when(?string $utc): string
    {
        return self::parse($utc)?->format('j M Y, g:i A') ?? '-';
    }

    public static function day(?string $utc): string
    {
        return self::parse($utc)?->format('j M Y') ?? '-';
    }

    /**
     * The word an editor sees for an item: DRAFT / PUBLISHED / SCHEDULED (published with a future date) / ARCHIVED.
     *
     * @param  array<string, mixed>  $item
     */
    public static function status(array $item): string
    {
        $s = strtoupper((string) ($item['status'] ?? ''));
        if ($s === 'PUBLISHED' && ($at = self::parse($item['publishedAt'] ?? null)) && $at->isFuture()) {
            return 'SCHEDULED';
        }

        return $s !== '' ? $s : 'DRAFT';
    }

    public static function statusLabel(string $status): string
    {
        return ['DRAFT' => 'Draft', 'PUBLISHED' => 'Published', 'SCHEDULED' => 'Scheduled', 'ARCHIVED' => 'Archived'][$status] ?? ucfirst(strtolower($status));
    }

    /**
     * Media object for an id out of a `media` map (settings / home sections) or an inline object; null when unknown.
     *
     * @param  array<string, mixed>  $map
     * @return array<string, mixed>|null
     */
    public static function media(mixed $ref, array $map = []): ?array
    {
        if (is_array($ref) && isset($ref['url'])) {
            return $ref;
        }
        if (is_string($ref) && $ref !== '' && isset($map[$ref]) && is_array($map[$ref])) {
            return $map[$ref];
        }

        return null;
    }

    /**
     * Smallest variant at least $width wide (falls back to the original): used for grid thumbnails.
     *
     * @param  array<string, mixed>|null  $media
     */
    public static function thumb(?array $media, int $width = 480): string
    {
        if (! $media) {
            return '';
        }
        $best = null;
        foreach ((array) ($media['variants'] ?? []) as $v) {
            if (! is_array($v) || empty($v['url'])) {
                continue;
            }
            if ((int) ($v['width'] ?? 0) >= $width && ($best === null || (int) $v['width'] < (int) $best['width'])) {
                $best = $v;
            }
        }

        return (string) ($best['url'] ?? $media['url'] ?? '');
    }

    /** Link to the public website (env R007_SITE_URL), for "preview on site". */
    public static function siteUrl(string $path = ''): string
    {
        return rtrim((string) config('r007.site_url', ''), '/').'/'.ltrim($path, '/');
    }

    /** First non-empty of several candidate keys (defensive against renamed fields). */
    public static function pick(array $row, string ...$keys): mixed
    {
        foreach ($keys as $k) {
            if (isset($row[$k]) && $row[$k] !== '') {
                return $row[$k];
            }
        }

        return null;
    }

    /**
     * Cursor-page numbers for the "Showing N" footer.
     *
     * @param  array<string, mixed>  $body
     * @return list<array<string, mixed>>
     */
    public static function items(array $body): array
    {
        $items = $body['items'] ?? (array_is_list($body) ? $body : []);

        return array_values(array_filter((array) $items, 'is_array'));
    }
}
