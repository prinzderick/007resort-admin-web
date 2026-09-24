<?php

namespace App\Livewire;

use App\Auth\StaffSession;
use App\Services\R007Api\R007ApiClient;
use App\Support\Fetch;
use Livewire\Component;

/** The pending-approvals count next to "Approvals" in the sidebar. */
class ApprovalsBadge extends Component
{
    public function render(R007ApiClient $api, StaffSession $staff)
    {
        $n = 0;
        if ($staff->canApproveAnything()) {
            $n = count(Fetch::of(fn () => $api->get('approvals', ['filter[status]' => 'PENDING', 'scope' => 'approvable', 'limit' => 100]), ['GET', '/approvals'])->items());
        }

        return view('livewire.approvals-badge', ['n' => $n]);
    }
}
