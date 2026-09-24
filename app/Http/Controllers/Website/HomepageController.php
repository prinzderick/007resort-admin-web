<?php

namespace App\Http\Controllers\Website;

use App\Support\Cms\Cms;
use App\Support\Cms\HomeBlocks;
use Illuminate\Http\Request;

/**
 * Homepage builder: blocks grouped by type, ordered within their type, each switched on or off on its own.
 * New blocks start switched off (the API's rule); reordering a type re-uses that type's existing sort values so the blocks keep their place among the others.
 */
class HomepageController extends WebsiteController
{
    public function index()
    {
        $res = $this->cms->fetch('home-sections', ['limit' => 200]);
        $items = Cms::items((array) $res->data);
        usort($items, fn ($a, $b) => ($a['sortOrder'] ?? 0) <=> ($b['sortOrder'] ?? 0));
        $by = [];
        foreach ($items as $i) {
            $by[strtoupper((string) ($i['type'] ?? ''))][] = $i;
        }

        return view('pages.website.homepage', [
            'types' => HomeBlocks::all(), 'by' => $by, 'media' => (array) ($res->data['media'] ?? []), 'loaded' => $res,
            'canManage' => $this->can(Cms::MANAGE), 'canPublish' => $this->can(Cms::PUBLISH),
        ]);
    }

    public function store(Request $request)
    {
        $type = $this->type($request);
        $body = ['type' => $type, 'payload' => $this->payload($type, $request)];
        $this->cms->post('home-sections', $body);

        return redirect()->to(route('website.homepage').'#'.strtolower($type))->with('success', 'Added. It is switched off for now: turn it on when you are ready for visitors to see it.');
    }

    public function update(Request $request, string $id)
    {
        $type = $this->type($request);
        $this->cms->patch("home-sections/{$id}", ['payload' => $this->payload($type, $request)], $request->input('rowVersion'));

        return redirect()->to(route('website.homepage').'#'.strtolower($type))->with('success', 'Saved.');
    }

    public function toggle(string $id, string $state)
    {
        $this->cms->post("home-sections/{$id}/{$state}");

        return back()->with('success', $state === 'enable' ? 'Switched on: it is now on the website.' : 'Switched off: it is hidden from the website.');
    }

    public function destroy(string $id)
    {
        $this->cms->delete("home-sections/{$id}");

        return back()->with('success', 'Removed.');
    }

    /** Save the order of one type: `ids[]` in the new order. */
    public function reorder(Request $request)
    {
        $type = $this->type($request);
        $ids = array_values(array_filter((array) $request->input('ids', []), 'is_string'));
        $current = collect(Cms::items($this->cms->get('home-sections', ['type' => $type, 'limit' => 200])))->keyBy('id');
        $ids = array_values(array_filter($ids, fn ($id) => $current->has($id)));
        $slots = $current->pluck('sortOrder')->map(fn ($v) => (int) $v)->sort()->values()->all();
        if (count(array_unique($slots)) < count($slots)) {
            $base = (int) ($slots[0] ?? 10);
            $slots = array_map(fn ($i) => $base + $i * 10, array_keys($slots));
        }
        $items = [];
        foreach ($ids as $i => $id) {
            $items[] = ['id' => $id, 'sortOrder' => $slots[$i] ?? (($slots[0] ?? 0) + $i * 10)];
        }
        if ($items !== []) {
            $this->cms->post('home-sections/reorder', ['items' => $items]);
        }

        return redirect()->to(route('website.homepage').'#'.strtolower($type))->with('success', 'Order saved.');
    }

    private function type(Request $request): string
    {
        $type = strtoupper((string) $request->input('type'));
        abort_unless(isset(HomeBlocks::all()[$type]), 422, 'Unknown block type.');

        return $type;
    }

    /** @return array<string, mixed> */
    private function payload(string $type, Request $request): array
    {
        $in = (array) $request->input('payload', []);
        $out = [];
        foreach (HomeBlocks::all()[$type]['fields'] as $f) {
            $raw = $in[$f['key']] ?? null;
            $v = is_string($raw) ? trim($raw) : $raw;
            $out[$f['key']] = match (true) {
                $v === null || $v === '' => null,
                $f['key'] === 'rating' => (int) $v,
                default => $v,
            };
        }

        return $out;
    }
}
