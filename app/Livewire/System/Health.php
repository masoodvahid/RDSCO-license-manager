<?php

namespace App\Livewire\System;

use App\Models\ActivityLog;
use App\Services\Shahkar\ShahkarCircuit;
use App\Services\System\HealthCheck;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('سلامت سامانه')]
class Health extends Component
{
    public function resetCircuit(ShahkarCircuit $circuit): void
    {
        $circuit->reset();

        ActivityLog::record('shahkar.circuit_reset', 'بازگشایی دستی استعلام‌های شاهکار');
        $this->dispatch('toast', type: 'success', message: 'استعلام‌های شاهکار دوباره فعال شد.');
    }

    public function render(HealthCheck $health)
    {
        return view('livewire.system.health', [
            'checks' => $health->runtime(),
            'circuit' => $health->shahkarCircuit(),
            'tables' => $health->tableSizes(),
        ]);
    }
}
