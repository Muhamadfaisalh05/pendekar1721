@props(['title', 'subtitle' => null])

<div class="mb-8">
    <div class="section-divider pb-3">
        <h2 class="text-xl font-bold text-gray-900">{{ $title }}</h2>
        @if($subtitle)
            <p class="text-sm text-gray-500 mt-1">{{ $subtitle }}</p>
        @endif
    </div>
</div>
