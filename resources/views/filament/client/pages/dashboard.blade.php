<x-filament-panels::page>
    @php
        $stats = $this->getStatistik();
        $pelatihanSaya = auth()->user()->userTrainings()->orderBy('title')->get();
    @endphp

    {{-- Greeting Header --}}
    <div class="mb-6">
        <h2 class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white">
            Selamat Datang, {{ auth()->user()->name }}
        </h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
            Ringkasan akun dan kegiatan Anda di PENDEKAR 1721.
        </p>
    </div>

    {{-- Statistik: agregat & milik sendiri, tanpa data pribadi orang lain --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <x-filament::card class="flex items-center">
            <div class="p-3 bg-primary-100 text-primary-600 rounded-lg dark:bg-primary-500/20 dark:text-primary-400">
                <x-heroicon-o-users class="w-6 h-6" />
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Jumlah Alumni</p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $stats['total_alumni'] }}</p>
                @if ($stats['total_alumni_belum_final'])
                    {{-- Fallback: definisi lama (client + published) sampai `users.graduated_at` terisi lewat backfill. --}}
                    <p class="text-[11px] leading-tight text-warning-600 dark:text-warning-400">
                        Angka sementara (belum final)
                    </p>
                @endif
            </div>
        </x-filament::card>

        <x-filament::card class="flex items-center">
            <div class="p-3 bg-info-100 text-info-600 rounded-lg dark:bg-info-500/20 dark:text-info-400">
                <x-heroicon-o-academic-cap class="w-6 h-6" />
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Pelatihan Saya</p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $stats['pelatihan_saya'] }}</p>
            </div>
        </x-filament::card>

        <x-filament::card class="flex items-center">
            <div class="p-3 bg-success-100 text-success-600 rounded-lg dark:bg-success-500/20 dark:text-success-400">
                <x-heroicon-o-identification class="w-6 h-6" />
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Kelengkapan Profil</p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $stats['kelengkapan_profil'] }}%</p>
            </div>
        </x-filament::card>
    </div>

    {{-- Konten milik sendiri --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
        <x-filament::card>
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Pelatihan yang Anda Ikuti</h3>

            @if ($pelatihanSaya->isNotEmpty())
                <ul class="space-y-2 text-sm text-gray-700 dark:text-gray-300">
                    @foreach ($pelatihanSaya as $pelatihan)
                        <li class="flex items-center gap-2">
                            <x-heroicon-o-check-circle class="w-4 h-4 text-success-600" />
                            {{ $pelatihan->title }}
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="flex flex-col items-center justify-center py-8 text-gray-500">
                    <x-heroicon-o-inbox class="w-12 h-12 mb-3 text-gray-400" />
                    <p>Belum ada pelatihan yang tercatat untuk akun Anda.</p>
                </div>
            @endif
        </x-filament::card>

        <x-filament::card class="bg-primary-50 border-primary-100 dark:bg-primary-900/10 dark:border-primary-800">
            <div class="flex items-start gap-3">
                <x-heroicon-o-information-circle class="w-5 h-5 text-primary-600 dark:text-primary-400 mt-0.5" />
                <div>
                    <h4 class="text-sm font-semibold text-primary-900 dark:text-primary-300 mb-1">Lengkapi Profil Anda</h4>
                    <p class="text-xs text-primary-700 dark:text-primary-400 leading-relaxed mb-3">
                        Data profil membantu admin menyalurkan Anda ke pelatihan dan penempatan kerja yang sesuai.
                        Perbarui data diri, pendidikan, dan pengalaman kerja Anda secara berkala.
                    </p>
                    <x-filament::button
                        tag="a"
                        href="{{ \App\Filament\Client\Pages\UserProfileEditPage::getUrl() }}"
                        icon="heroicon-o-pencil-square"
                        size="sm"
                    >
                        Ubah Profil
                    </x-filament::button>
                </div>
            </div>
        </x-filament::card>
    </div>
</x-filament-panels::page>
