@props(['label' => null, 'name', 'hint' => null, 'rows' => 3, 'ltr' => false])

@php($id = 'field-'.str_replace('.', '-', $name))

<div {{ $attributes->only('class') }}>
    @if ($label)
        <label for="{{ $id }}" class="mb-1.5 block text-sm font-medium text-gray-700">{{ $label }}</label>
    @endif
    <textarea
        id="{{ $id }}"
        rows="{{ $rows }}"
        wire:model="{{ $name }}"
        @if ($ltr) dir="ltr" @endif
        {{ $attributes->except('class')->class(['field', 'field-error' => $errors->has($name), 'text-left font-mono text-xs' => $ltr]) }}
    ></textarea>
    @error($name)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @else
        @if ($hint)
            <p class="mt-1 text-xs text-gray-500">{{ $hint }}</p>
        @endif
    @enderror
</div>
