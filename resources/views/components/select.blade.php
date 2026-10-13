@props(['label' => null, 'name', 'options' => [], 'placeholder' => null, 'hint' => null, 'live' => false, 'required' => false])

@php($id = 'field-'.str_replace('.', '-', $name))

<div {{ $attributes->only('class') }}>
    @if ($label)
        <label for="{{ $id }}" class="mb-1.5 block text-sm font-medium text-gray-700">
            {{ $label }}@if ($required)<span class="text-red-500"> *</span>@endif
        </label>
    @endif
    <select
        id="{{ $id }}"
        @if ($live) wire:model.live="{{ $name }}" @else wire:model="{{ $name }}" @endif
        {{ $attributes->except('class')->class(['field pe-8', 'field-error' => $errors->has($name)]) }}
    >
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $value => $optionLabel)
            <option value="{{ $value }}">{{ $optionLabel }}</option>
        @endforeach
    </select>
    @error($name)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @else
        @if ($hint)
            <p class="mt-1 text-xs text-gray-500">{{ $hint }}</p>
        @endif
    @enderror
</div>
