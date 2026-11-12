{{--
    Akses cepat dashboard Admin — empat tautan utama.
    Gradient tombol memakai inline style (panel tidak memakai tema Tailwind kustom).
--}}
<x-filament-widgets::widget>
    <x-filament::section heading="Akses Cepat" icon="heroicon-o-bolt" icon-color="warning">
        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($tautan as $item)
                <a href="{{ $item['url'] }}" class="flex items-start gap-3 rounded-xl p-4"
                   style="border:1px solid #e5e7eb; background:#ffffff; box-shadow:0 1px 2px rgba(0,0,0,.05);">
                    <span class="flex items-center justify-center rounded-lg flex-none"
                          style="width:2.5rem; height:2.5rem; background:{{ $item['latar'] }}; color:#ffffff;">
                        <x-filament::icon :icon="$item['ikon']" class="h-5 w-5" />
                    </span>
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold" style="color:#111827;">{{ $item['label'] }}</span>
                        <span class="block text-xs mt-1" style="color:#6b7280;">{{ $item['keterangan'] }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
