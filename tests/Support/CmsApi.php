<?php

namespace Tests\Support;

/**
 * The Website CMS admin API as it REALLY answers: JSON recorded from a running node with
 * `php artisan r007:capture-cms-fixtures` into tests/Fixtures/cms (real field names, expanded media, counts, problem bodies).
 */
final class CmsApi
{
    public const ALL = ['cms.view', 'cms.manage', 'cms.publish', 'cms.media.manage', 'cms.subscribers.view', 'cms.subscribers.export', 'cms.messages.manage'];

    /** What a website editor without publish/export rights gets. */
    public const EDITOR = ['cms.view', 'cms.manage', 'cms.media.manage'];

    public const VIEWER = ['cms.view'];

    /** @return array<mixed> */
    public static function load(string $name): array
    {
        $file = __DIR__.'/../Fixtures/cms/'.$name.'.json';

        return is_file($file) ? (array) json_decode((string) file_get_contents($file), true) : [];
    }

    public static function csv(string $name): string
    {
        return (string) file_get_contents(__DIR__.'/../Fixtures/cms/'.$name.'.csv');
    }

    /** @return array<mixed> the problem body of a recorded failure */
    public static function problem(string $name): array
    {
        $f = self::load($name.'.error');

        return $f['__problem'] ?? [];
    }

    /** [status, body] of a recorded failure, ready for fakeApi(). @return array{0: int, 1: array<mixed>} */
    public static function failure(string $name): array
    {
        $f = self::load($name.'.error');

        return [(int) ($f['__status'] ?? 500), $f['__problem'] ?? []];
    }

    /** First item of a recorded list. @return array<string, mixed> */
    public static function first(string $list, ?string $status = null): array
    {
        foreach (self::load($list)['items'] ?? [] as $i) {
            if ($status === null || ($i['status'] ?? null) === $status) {
                return $i;
            }
        }

        return [];
    }

    /**
     * Every read the Website screens make, answered with the recordings. More specific patterns first.
     *
     * @param  array<string, mixed>  $over
     * @return array<string, mixed>
     */
    public static function routes(array $over = []): array
    {
        $r = fn (string $n) => self::load($n);
        $b = '/admin/cms';

        return $over + [
            "GET {$b}/summary" => $r('summary'),
            "GET {$b}/meta" => $r('meta'),
            "GET {$b}/settings" => $r('settings'),
            "GET {$b}/settings/*" => $r('settings-brand'),
            "GET {$b}/home-sections" => $r('home-sections'),
            "GET {$b}/home-sections/*" => $r('home-section'),
            "GET {$b}/pages/*" => $r('page-published'),
            "GET {$b}/pages" => $r('pages-list'),
            "GET {$b}/posts/*" => $r('post-published'),
            "GET {$b}/posts" => $r('posts-list'),
            "GET {$b}/events/*" => $r('event-published'),
            "GET {$b}/events" => $r('events-list'),
            "GET {$b}/post-categories/*" => $r('post-category'),
            "GET {$b}/post-categories" => $r('post-categories'),
            "GET {$b}/gallery/albums/*/items" => $r('album-items'),
            "GET {$b}/gallery/albums/*" => $r('album'),
            "GET {$b}/gallery/albums" => $r('albums'),
            "GET {$b}/media/*/usage" => $r('media-usage'),
            "GET {$b}/media/*" => $r('media-one'),
            "GET {$b}/media" => $r('media-list'),
            "GET {$b}/subscribers" => $r('subscribers'),
            "GET {$b}/messages/*" => $r('message'),
            "GET {$b}/messages" => $r('messages'),
            'GET /organization/facilities' => RealApi::load('organization-facilities'),
            'GET /admin/catalog/products' => RealApi::load('admin-catalog-products'),
        ];
    }
}
