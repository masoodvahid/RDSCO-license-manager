<?php

namespace App\Livewire\Errors;

use App\Enums\ErrorStatus;
use App\Models\AppError;
use Livewire\Component;

class Show extends Component
{
    public AppError $error;

    public function mount(AppError $error): void
    {
        $this->error = $error;
    }

    public function setStatus(string $status): void
    {
        $status = ErrorStatus::from($status);
        $this->error->update([
            'status' => $status,
            'resolved_at' => $status === ErrorStatus::Resolved ? now() : null,
        ]);

        $this->dispatch('toast', type: 'success', message: "وضعیت خطا «{$status->label()}» شد.");
    }

    public function delete(): void
    {
        $this->error->delete();
        session()->flash('toast', ['type' => 'success', 'message' => 'خطا حذف شد.']);
        $this->redirectRoute('errors.index', navigate: true);
    }

    public function render()
    {
        $this->error->load(['customer', 'product', 'license']);

        return view('livewire.errors.show')->title('جزئیات خطا');
    }
}
