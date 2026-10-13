<?php

namespace App\Livewire\Activity;

use App\Models\ActivityLog;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('گزارش فعالیت')]
class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.activity.index', [
            'logs' => ActivityLog::query()
                ->with('user')
                ->when(trim($this->search) !== '', fn ($query) => $query->where('description', 'like', '%'.trim($this->search).'%'))
                ->latest('id')
                ->paginate(50),
        ]);
    }
}
