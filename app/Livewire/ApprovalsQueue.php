<?php

namespace App\Livewire;

use App\Auth\StaffSession;
use App\Services\R007Api\R007ApiClient;
use App\Services\R007Api\R007ApiException;
use App\Support\Fetch;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Approvals queue: sensitive actions (void, refund, reversal, stock adjustment,
 * discount ...) that returned 202 PENDING_APPROVAL land here. The decision is
 * always made by the API, against the approver's own permission.
 */
#[Layout('components.layouts.app')]
#[Title('Approvals')]
class ApprovalsQueue extends Component
{
    public string $tab = 'pending';

    /** @var array<string, string> */
    public array $notes = [];

    public ?string $message = null;

    public string $messageTone = 'ok';

    public function decide(string $id, string $decision, R007ApiClient $api): void
    {
        abort_unless(in_array($decision, ['APPROVE', 'REJECT'], true), 422);

        try {
            $res = $api->request('POST', "approvals/{$id}/decision", [], array_filter([
                'decision' => $decision,
                'note' => trim($this->notes[$id] ?? '') ?: null,
            ]));
            $this->message = $decision === 'APPROVE' ? 'Approved. The action has been carried out by the API.' : 'Rejected. The requester will see the rejection.';
            $this->messageTone = 'ok';
            unset($this->notes[$id]);
        } catch (R007ApiException $e) {
            $this->message = $e->isForbidden()
                ? 'You do not hold the permission required to decide this request.'
                : ($e->status === 409 ? 'This request was already decided by someone else.' : ($e->detail ?: $e->title));
            $this->messageTone = 'error';
        }
    }

    public function cancel(string $id, R007ApiClient $api): void
    {
        try {
            $api->post("approvals/{$id}/cancel");
            $this->message = 'Request cancelled.';
            $this->messageTone = 'ok';
        } catch (R007ApiException $e) {
            $this->message = $e->detail ?: $e->title;
            $this->messageTone = 'error';
        }
    }

    public function render(R007ApiClient $api, StaffSession $staff)
    {
        // The API lists PENDING only unless filter[status] says otherwise, so the history tab must ask for the decided ones.
        $query = $this->tab === 'pending'
            ? ['filter[status]' => 'PENDING', 'scope' => 'approvable', 'limit' => 100]
            : ['filter[status]' => 'APPROVED,REJECTED,CANCELLED,EXPIRED', 'limit' => 100];
        $list = Fetch::of(fn () => $api->get('approvals', $query), ['GET', '/approvals']);
        $items = $list->items();

        return view('livewire.approvals-queue', ['list' => $list, 'items' => $items, 'staff' => $staff]);
    }
}
