<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Record REAL Website CMS admin API responses (docs/CMS_API.md section 4) from a running node into tests/Fixtures/cms/, so the Website
 * screens are tested against what the API really sends (field names, expanded media, counts, problem bodies), not against the contract text.
 * Only reads and requests that the API refuses are sent, so a demo database is left unchanged. Secrets are redacted.
 *
 *   php artisan r007:capture-cms-fixtures --base=http://127.0.0.1:8073 --user=owner1 --password='...'
 */
class CaptureCmsFixtures extends Command
{
    protected $signature = 'r007:capture-cms-fixtures {--base=http://127.0.0.1:8080} {--user=owner1} {--password=} {--low-user=cashier1} {--low-password=} {--out=}';

    protected $description = 'Capture real Website CMS API responses into tests/Fixtures/cms (secrets redacted)';

    private string $base;

    private string $out;

    private string $token = '';

    public function handle(): int
    {
        $this->base = rtrim((string) $this->option('base'), '/').'/api/v1/';
        $this->out = (string) ($this->option('out') ?: base_path('tests/Fixtures/cms'));
        @mkdir($this->out, 0775, true);
        $this->token = $this->login((string) $this->option('user'), (string) $this->option('password'));
        if ($this->token === '') {
            $this->error('Login failed.');

            return self::FAILURE;
        }
        $this->grab('auth-me', 'auth/me', prefix: '');
        $b = 'admin/cms/';
        $this->grab('meta', $b.'meta');
        $this->grab('summary', $b.'summary');
        $this->grab('settings', $b.'settings');
        $this->grab('settings-brand', $b.'settings/brand');
        $home = $this->grab('home-sections', $b.'home-sections', ['limit' => 200]);
        if ($id = $home['items'][0]['id'] ?? null) {
            $this->grab('home-section', $b."home-sections/{$id}");
        }
        foreach (['pages' => 'pages', 'posts' => 'posts', 'events' => 'events'] as $name => $path) {
            $list = $this->grab("{$name}-list", $b.$path, ['limit' => 50]);
            $pub = $this->pick($list['items'] ?? [], 'PUBLISHED');
            if ($pub) {
                $this->grab(rtrim($name, 's').'-published', $b."{$path}/{$pub['id']}");
                $this->grab("{$name}-published-delete", $b."{$path}/{$pub['id']}", method: 'DELETE');
            }
            if ($draft = $this->pick($list['items'] ?? [], 'DRAFT')) {
                $this->grab(rtrim($name, 's').'-draft', $b."{$path}/{$draft['id']}");
            }
            if ($pub) {
                $this->grab("{$name}-stale-update", $b."{$path}/{$pub['id']}", ['x' => 1], 'PATCH', ['If-Match' => '"0"']);
                $this->grab("{$name}-no-if-match", $b."{$path}/{$pub['id']}", ['title' => 'x'], 'PATCH');
            }
        }
        $this->grab('pages-create-invalid', $b.'pages', [], 'POST');
        $this->grab('events-create-invalid', $b.'events', ['title' => 'x', 'category' => 'MUSIC', 'startsAt' => '2026-10-10T18:00:00Z', 'endsAt' => '2026-10-10T17:00:00Z'], 'POST');
        $cats = $this->grab('post-categories', $b.'post-categories');
        if ($cat = $cats['items'][0]['id'] ?? null) {
            $this->grab('post-category', $b."post-categories/{$cat}");
        }
        $albums = $this->grab('albums', $b.'gallery/albums');
        if ($aid = $albums['items'][0]['id'] ?? null) {
            $this->grab('album', $b."gallery/albums/{$aid}");
            $this->grab('album-items', $b."gallery/albums/{$aid}/items");
        }
        $media = $this->grab('media-list', $b.'media', ['limit' => 20]);
        if ($mid = $media['items'][0]['id'] ?? null) {
            $this->grab('media-one', $b."media/{$mid}");
            $this->grab('media-usage', $b."media/{$mid}/usage");
            $this->grab('media-delete-in-use', $b."media/{$mid}", method: 'DELETE');
        }
        $this->grab('subscribers', $b.'subscribers', ['limit' => 50]);
        $this->grab('subscribers-confirmed', $b.'subscribers', ['limit' => 50, 'status' => 'CONFIRMED']);
        $this->raw('subscribers-export', $b.'subscribers/export', ['status' => 'CONFIRMED']);
        $msgs = $this->grab('messages', $b.'messages', ['limit' => 50]);
        if ($m = $msgs['items'][0]['id'] ?? null) {
            $this->grab('message', $b."messages/{$m}");
        }
        // A person without CMS permissions: what a 403 looks like.
        if ((string) $this->option('low-password') !== '') {
            $low = $this->login((string) $this->option('low-user'), (string) $this->option('low-password'));
            if ($low !== '') {
                $save = $this->token;
                $this->token = $low;
                $this->grab('forbidden', $b.'summary');
                $this->token = $save;
            }
        }
        $this->info("Recorded to {$this->out}");

        return self::SUCCESS;
    }

    private function login(string $user, string $password): string
    {
        $r = Http::acceptJson()->post($this->base.'auth/staff/login', ['credentialType' => 'PASSWORD', 'identifier' => $user, 'secret' => $password]);

        return $r->failed() ? '' : (string) $r->json('accessToken');
    }

    /**
     * @param  array<string, mixed>  $data  query for GET, body otherwise
     * @param  array<string, string>  $headers
     * @return array<mixed>
     */
    private function grab(string $name, string $path, array $data = [], string $method = 'GET', array $headers = [], string $prefix = ''): array
    {
        $req = Http::acceptJson()->withToken($this->token)->withHeaders($headers + ($method === 'GET' ? [] : ['Idempotency-Key' => (string) Str::uuid()]));
        $r = $method === 'GET' ? $req->get($this->base.$path, $data) : $req->send($method, $this->base.$path, ['json' => $data]);
        $body = $r->status() === 204 ? [] : (array) $r->json();
        $json = CaptureFixtures::redact($body);
        if ($r->failed()) {
            $json = ['__status' => $r->status(), '__problem' => $json];
            $name .= '.error';
        }
        file_put_contents("{$this->out}/{$name}.json", json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
        $this->line(($r->failed() ? 'err ' : 'ok  ').$name.' ('.$r->status().')');

        return $r->failed() ? [] : $json;
    }

    /** @param  array<string, mixed>  $query */
    private function raw(string $name, string $path, array $query): void
    {
        $r = Http::withToken($this->token)->accept('text/csv')->get($this->base.$path, $query);
        file_put_contents("{$this->out}/{$name}.csv", $r->body());
        $this->line('csv '.$name.' ('.$r->status().')');
    }

    /** @param  list<array<string, mixed>>  $items */
    private function pick(array $items, string $status): ?array
    {
        foreach ($items as $i) {
            if (($i['status'] ?? null) === $status) {
                return $i;
            }
        }

        return null;
    }
}
