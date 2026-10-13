@props(['color' => 'gray'])

<span {{ $attributes->class([
    'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset whitespace-nowrap',
    'bg-gray-50 text-gray-600 ring-gray-200' => $color === 'gray',
    'bg-emerald-50 text-emerald-700 ring-emerald-200' => $color === 'success',
    'bg-amber-50 text-amber-700 ring-amber-200' => $color === 'warning',
    'bg-red-50 text-red-700 ring-red-200' => $color === 'danger',
    'bg-brand-50 text-brand-700 ring-brand-200' => $color === 'info',
]) }}>{{ $slot }}</span>
