<?php

namespace App\Livewire;

use App\Services\Cms\CmsApi;
use App\Services\Portal\DashboardData;
use App\Services\Portal\SetupProgress;
use App\Support\DateRange;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** The owner dashboard. The period comes from the top-bar date range (?range=7d or ?from=&to=). */
#[Layout('components.layouts.app', ['range' => true])]
#[Title('Dashboard')]
class Dashboard extends Component
{
    /** Frozen at mount so a poll refresh keeps the period the page was opened with. */
    public string $from = '';

    public string $to = '';

    public function mount(): void
    {
        $r = DateRange::fromRequest();
        $this->from = $r->from;
        $this->to = $r->to;
    }

    public function render(DashboardData $data, SetupProgress $setup, CmsApi $cms)
    {
        $range = new DateRange($this->from, $this->to, 'custom');
        $d = $data->build($range);
        $d['range'] = $range;
        $onboarding = auth_staff()->canAny('config.manage', 'facility.configure') ? $setup->get() : null;

        // The small "Website" card: only for people who can see website content; a failure here never breaks the dashboard.
        $website = auth_staff()->can('cms.view') ? $cms->fetch('summary') : null;

        return view('livewire.dashboard', ['d' => $d, 'onboarding' => $onboarding, 'website' => $website]);
    }
}
