<?php

namespace App\Livewire;

use App\Services\Portal\DashboardData;
use App\Support\Time;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    public string $date = '';

    public function mount(): void
    {
        $this->date = Time::today();
    }

    public function render(DashboardData $data)
    {
        return view('livewire.dashboard', ['d' => $data->build($this->date)]);
    }
}
