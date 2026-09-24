<?php

namespace App\Http\Controllers\Website;

use App\Support\Cms\Cms;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Newsletter subscribers: counts, growth, search and filters, CSV export (own permission), unsubscribe and permanent erase. */
class SubscribersController extends WebsiteController
{
    public const STATUS = ['' => 'All', 'CONFIRMED' => 'Confirmed', 'PENDING' => 'Waiting to confirm', 'UNSUBSCRIBED' => 'Unsubscribed'];

    public const SOURCES = ['footer' => 'Website footer', 'blog' => 'Blog', 'event' => 'Event page', 'popup' => 'Pop-up', 'checkout' => 'Checkout'];

    public function index(Request $request)
    {
        $query = ['limit' => $this->perPage($request, 50), 'cursor' => $request->query('cursor')];
        foreach (['q', 'status', 'source'] as $k) {
            if (is_string($request->query($k)) && $request->query($k) !== '') {
                $query[$k] = $request->query($k);
            }
        }
        $list = $this->cms->fetch('subscribers', $query);
        $counts = (array) ($list->data['counts'] ?? []);

        return view('pages.website.subscribers', [
            'list' => $list, 'items' => Cms::items((array) $list->data), 'next' => $list->next(), 'counts' => $counts, 'growth' => $this->growth(), 'chips' => self::STATUS, 'sources' => self::SOURCES,
            'canManage' => $this->can(Cms::MANAGE), 'canExport' => $this->can(Cms::EXPORT),
        ]);
    }

    /** CSV straight from the API (it is formula-injection safe and audited on its side). */
    public function export(Request $request): StreamedResponse
    {
        $status = in_array($request->query('status'), ['CONFIRMED', 'PENDING', 'UNSUBSCRIBED'], true) ? $request->query('status') : null;
        $res = $this->cms->csv('subscribers/export', array_filter(['status' => $status]));
        $name = 'subscribers'.($status ? '-'.strtolower($status) : '').'-'.now(Cms::TZ)->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($res): void {
            echo $res['body'];
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function unsubscribe(string $id)
    {
        $this->cms->post("subscribers/{$id}/unsubscribe");

        return back()->with('success', 'Unsubscribed. They will not receive emails, and the address is kept so they are not added again by mistake.');
    }

    public function destroy(string $id)
    {
        $this->cms->delete("subscribers/{$id}");

        return back()->with('success', 'Erased for good. Only a scrambled fingerprint of the address is kept in the audit log.');
    }

    /**
     * Cumulative new sign-ups over the last 30 days (Lagos dates), from the newest sign-ups the API returns (up to 1,000).
     *
     * @return array{spark: list<int>, added: int, capped: bool}
     */
    private function growth(): array
    {
        try {
            $all = $this->cms->all('subscribers', [], 5);
        } catch (\Throwable) {
            return ['spark' => [], 'added' => 0, 'capped' => false];
        }
        $today = CarbonImmutable::now(Cms::TZ)->startOfDay();
        $days = [];
        for ($i = 29; $i >= 0; $i--) {
            $days[$today->subDays($i)->format('Y-m-d')] = 0;
        }
        foreach ($all['items'] as $s) {
            $d = Cms::parse($s['createdAt'] ?? null)?->format('Y-m-d');
            if ($d !== null && isset($days[$d])) {
                $days[$d]++;
            }
        }
        $run = 0;
        $spark = [];
        foreach ($days as $n) {
            $run += $n;
            $spark[] = $run;
        }

        return ['spark' => $spark, 'added' => $run, 'capped' => $all['nextCursor'] !== null];
    }
}
