@props(['label' => null, 'name', 'hint' => null, 'type' => 'text', 'ltr' => false, 'required' => false, 'modifier' => null])

@php($id = $attributes->get('id', 'field-'.str_replace('.', '-', $name)))

<div {{ $attributes->only('class') }}>
    @if ($label)
        <label for="{{ $id }}" class="mb-1.5 block text-sm font-medium text-gray-700">
            {{ $label }}@if ($required)<span class="text-red-500"> *</span>@endif
        </label>
    @endif
    <input
        id="{{ $id }}"
        type="{{ $type }}"
        wire:model{{ $modifier ? '.'.$modifier : '' }}="{{ $name }}"
        @if ($ltr) dir="ltr" @endif
        {{ $attributes->except(['class', 'id'])->class(['field', 'field-error' => $errors->has($name), 'text-left' => $ltr]) }}
    >
    @error($name)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @else
        @if ($hint)
            <p class="mt-1 text-xs text-gray-500">{{ $hint }}</p>
        @endif
    @enderror
</div>
