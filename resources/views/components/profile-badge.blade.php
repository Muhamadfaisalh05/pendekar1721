@props(['label', 'variant' => 'default'])

@php
    $classes = match($variant) {
        'primary' => 'bg-primary-100 text-primary-700 ring-primary-600/20',
        'green' => 'bg-green-50 text-green-700 ring-green-600/20',
        'amber' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        'red' => 'bg-red-50 text-red-700 ring-red-600/20',
        default => 'bg-gray-100 text-gray-700 ring-gray-600/10',
    };
@endphp

<span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium ring-1 ring-inset {{ $classes }}">
    {{ $label }}
</span>
