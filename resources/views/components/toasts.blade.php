{{-- Toasts from Livewire ($this->dispatch('toast', ...)) and from session flashes. --}}
<div
    x-data="{
        toasts: [],
        add(toast) {
            const id = Date.now() + Math.random();
            this.toasts.push({ id, type: toast.type ?? 'success', message: toast.message });
            setTimeout(() => this.remove(id), toast.type === 'error' ? 7000 : 4000);
        },
        remove(id) { this.toasts = this.toasts.filter((toast) => toast.id !== id); },
    }"
    x-init="@if (session('toast')) add(@js(session('toast'))) @endif"
    x-on:toast.window="add($event.detail)"
    class="pointer-events-none fixed bottom-4 left-4 z-50 flex w-80 max-w-[calc(100vw-2rem)] flex-col gap-2"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-transition
            class="pointer-events-auto flex items-start gap-3 rounded-xl border bg-white p-3 text-sm shadow-lg"
            :class="toast.type === 'error' ? 'border-red-200' : 'border-gray-200'"
        >
            <span class="mt-0.5 size-2 shrink-0 rounded-full" :class="toast.type === 'error' ? 'bg-red-500' : (toast.type === 'warning' ? 'bg-amber-500' : 'bg-emerald-500')"></span>
            <p class="flex-1 text-gray-700" x-text="toast.message"></p>
            <button type="button" class="text-gray-400 hover:text-gray-600" @click="remove(toast.id)" aria-label="بستن">&times;</button>
        </div>
    </template>
</div>
