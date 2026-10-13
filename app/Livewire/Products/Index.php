<?php

namespace App\Livewire\Products;

use App\Models\ActivityLog;
use App\Models\Product;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('محصولات')]
class Index extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $slug = '';

    public string $description = '';

    public bool $is_active = true;

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(Product $product): void
    {
        $this->resetForm();
        $this->editingId = $product->id;
        $this->name = $product->name;
        $this->slug = $product->slug;
        $this->description = (string) $product->description;
        $this->is_active = $product->is_active;
        $this->showForm = true;
    }

    public function updatedName(string $value): void
    {
        if ($this->editingId === null && $this->slug === '') {
            $this->slug = Str::slug($value);
        }
    }

    public function save(): void
    {
        $this->slug = Str::lower(trim($this->slug));

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9][a-z0-9-]*$/', Rule::unique('products', 'slug')->ignore($this->editingId)],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ], [
            'slug.regex' => 'شناسه فقط می‌تواند شامل حروف کوچک انگلیسی، عدد و خط تیره باشد.',
        ]);

        $product = Product::query()->updateOrCreate(['id' => $this->editingId], $data);
        ActivityLog::record($this->editingId ? 'product.updated' : 'product.created', "ذخیره محصول «{$product->name}»", $product);

        $this->showForm = false;
        $this->dispatch('toast', type: 'success', message: 'محصول ذخیره شد.');
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'slug', 'description']);
        $this->is_active = true;
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.products.index', [
            'products' => Product::query()->withCount('licenses')->orderBy('name')->get(),
        ]);
    }
}
