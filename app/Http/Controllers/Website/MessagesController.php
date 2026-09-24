<?php

namespace App\Http\Controllers\Website;

use App\Services\R007Api\R007ApiException;
use App\Support\Cms\Cms;
use Illuminate\Http\Request;

/** Contact-form inbox: statuses New / Read / Replied / Spam, a detail drawer with an internal note, bulk actions, reply by email (mailto). */
class MessagesController extends WebsiteController
{
    public const STATUS = ['' => 'All', 'NEW' => 'New', 'READ' => 'Read', 'REPLIED' => 'Replied', 'SPAM' => 'Spam'];

    public const TOPICS = ['GENERAL' => 'General', 'BOOKING' => 'Booking', 'EVENTS' => 'Events', 'MEMBERSHIP' => 'Membership', 'FEEDBACK' => 'Feedback', 'PRESS' => 'Press', 'OTHER' => 'Other'];

    public function index(Request $request)
    {
        $query = ['limit' => $this->perPage($request, 50), 'cursor' => $request->query('cursor')];
        foreach (['q', 'status', 'topic'] as $k) {
            if (is_string($request->query($k)) && $request->query($k) !== '') {
                $query[$k] = $request->query($k);
            }
        }
        $list = $this->cms->fetch('messages', $query);
        $counts = (array) ($list->data['counts'] ?? []);

        return view('pages.website.messages', [
            'list' => $list, 'items' => Cms::items((array) $list->data), 'next' => $list->next(), 'chips' => self::STATUS, 'topics' => self::TOPICS,
            'chipCounts' => ['' => array_sum(array_map('intval', $counts)) ?: null, 'NEW' => $counts['new'] ?? null, 'READ' => $counts['read'] ?? null, 'REPLIED' => $counts['replied'] ?? null, 'SPAM' => $counts['spam'] ?? null],
        ]);
    }

    public function update(Request $request, string $id)
    {
        $data = $request->validate(['status' => ['nullable', 'in:NEW,READ,REPLIED,SPAM'], 'internalNote' => ['nullable', 'string', 'max:2000']]);
        $body = [];
        if (! empty($data['status'])) {
            $body['status'] = $data['status'];
        }
        if ($request->has('internalNote')) {
            $body['internalNote'] = trim((string) ($data['internalNote'] ?? ''));
        }
        $this->cms->patch("messages/{$id}", $body, null);

        return back()->with('success', isset($body['status']) ? 'Marked as '.strtolower(self::STATUS[$body['status']]).'.' : 'Note saved.');
    }

    /** Apply one action to many messages: status changes, or erase. */
    public function bulk(Request $request)
    {
        $data = $request->validate(['ids' => ['required', 'array', 'min:1', 'max:100'], 'ids.*' => ['string'], 'action' => ['required', 'in:READ,REPLIED,SPAM,NEW,DELETE']]);
        $ok = 0;
        $failed = 0;
        foreach ($data['ids'] as $id) {
            try {
                $data['action'] === 'DELETE' ? $this->cms->delete("messages/{$id}") : $this->cms->patch("messages/{$id}", ['status' => $data['action']], null);
                $ok++;
            } catch (R007ApiException $e) {
                if ($e->isUnauthenticated()) {
                    throw $e;
                }
                $failed++;
            }
        }
        $verb = $data['action'] === 'DELETE' ? 'erased' : 'marked as '.strtolower(self::STATUS[$data['action']]);
        $back = back()->with('success', $ok.' message'.($ok === 1 ? '' : 's').' '.$verb.'.');

        return $failed ? $back->with('error', $failed.' could not be changed. Try those again.') : $back;
    }

    public function destroy(string $id)
    {
        $this->cms->delete("messages/{$id}");

        return back()->with('success', 'Message erased.');
    }
}
