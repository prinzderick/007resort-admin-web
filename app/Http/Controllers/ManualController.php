<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * The staff operations manual, inside the portal for everyone who is signed in (no permission needed: it is how people learn
 * their own screens). Same code on the online and the property portal, so it works on both and offline on the property.
 *
 * The text lives in resources/manual/staff-manual.md (one "## N. Title" per chapter); the designed PDF is built from the same file
 * by tools/manual (npm run build) and committed beside it.
 */
class ManualController extends Controller
{
    private const SOURCE = 'resources/manual/staff-manual.md';

    private const PDF = 'resources/manual/SERI-Resort-Staff-Manual.pdf';

    public function index()
    {
        return view('pages.manual', $this->manual());
    }

    public function pdf()
    {
        $path = base_path(self::PDF);
        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="SERI-Resort-Staff-Manual.pdf"',
        ]);
    }

    /**
     * @return array{chapters: list<array{n: int, title: string, html: string}>, roles: list<array{label: string, chapters: list<int>, target: int}>, hasPdf: bool}
     */
    private function manual(): array
    {
        $path = base_path(self::SOURCE);
        abort_unless(is_file($path), 404);

        $parsed = Cache::remember('manual:'.filemtime($path), 3600, fn () => $this->parse((string) file_get_contents($path)));

        return $parsed + ['hasPdf' => is_file(base_path(self::PDF))];
    }

    /**
     * @return array{chapters: list<array{n: int, title: string, html: string}>, roles: list<array{label: string, chapters: list<int>, target: int}>}
     */
    private function parse(string $markdown): array
    {
        $chapters = [];
        $roles = [];

        foreach (array_slice(preg_split('/^## /m', $markdown) ?: [], 1) as $block) {
            [$heading, $body] = array_pad(explode("\n", $block, 2), 2, '');
            if (! preg_match('/^(\d+)\.\s+(.*)$/', trim($heading), $m)) {
                continue;
            }
            $n = (int) $m[1];
            if ($n === 1) {
                $roles = $this->roles($body);
            }
            $chapters[] = ['n' => $n, 'title' => $m[2], 'html' => $this->decorate($this->render($body))];
        }

        return ['chapters' => $chapters, 'roles' => $roles];
    }

    private function render(string $markdown): string
    {
        return Str::markdown($markdown, ['html_input' => 'escape', 'allow_unsafe_links' => false]);  // Str::markdown is GitHub-flavoured: tables included
    }

    /** Wrap each "###" section so the warning boxes ("Never do this", "If something goes wrong") stand out, as in the PDF. */
    private function decorate(string $html): string
    {
        $parts = preg_split('/(?=<h3>)/', $html) ?: [$html];

        return implode('', array_map(function (string $chunk, int $i): string {
            $chunk = preg_replace('/<p>(<strong>Check with IT:<\/strong>.*?)<\/p>/s', '<p class="note-it">$1</p>', $chunk) ?? $chunk;
            if ($i === 0 && ! str_starts_with($chunk, '<h3>')) {
                $chunk = preg_replace('/<p>(<strong>Your job:<\/strong>.*?)<\/p>/s', '<p class="p-job">$1</p>', $chunk) ?? $chunk;

                return preg_replace('/<p>(<strong>Your device:<\/strong>.*?)<\/p>/s', '<p class="p-device">$1</p>', $chunk) ?? $chunk;
            }
            preg_match('/^<h3>(.*?)<\/h3>/', $chunk, $h);
            $class = match ($h[1] ?? '') {
                'Never do this' => 'sec sec-never',
                'If something goes wrong' => 'sec sec-wrong',
                default => 'sec',
            };

            return '<div class="'.$class.'">'.$chunk.'</div>';
        }, $parts, array_keys($parts)));
    }

    /**
     * "Find your role" shortcuts, read from the table in chapter 1 so they can never disagree with the text.
     *
     * Each role's own chapter (`target`) is the last one in 5..16, the role chapters; chapters 2, 17 and 18 are shared reading.
     *
     * @return list<array{label: string, chapters: list<int>, target: int}>
     */
    private function roles(string $chapterOne): array
    {
        $roles = [];
        foreach (preg_split('/\R/', $chapterOne) ?: [] as $line) {
            if (preg_match('/^\|\s*(.+?)\s*\|\s*(\d+(?:\s*,\s*\d+)*)\s*\|\s*$/', $line, $m)) {
                $chapters = array_map('intval', preg_split('/\s*,\s*/', $m[2]) ?: []);
                $own = array_filter($chapters, fn (int $c) => $c >= 5 && $c <= 16);
                $roles[] = ['label' => $m[1], 'chapters' => $chapters, 'target' => $own === [] ? $chapters[0] : max($own)];
            }
        }

        return $roles;
    }
}
