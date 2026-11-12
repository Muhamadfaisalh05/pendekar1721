<x-filament-panels::page>
    @php
        $stats = $this->getStatistik();
        $recentPeserta = $this->getPesertaTerbaru();
    @endphp

    {{-- Greeting Header --}}
    <div class="mb-6">
        <h2 class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white">
            Selamat Datang, {{ auth()->user()->name }}
        </h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
            Berikut adalah ringkasan data dan aktivitas di sistem Anda hari ini.
        </p>
    </div>

    {{-- Statistics Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <x-filament::card class="flex items-center">
            <div class="p-3 bg-primary-100 text-primary-600 rounded-lg dark:bg-primary-500/20 dark:text-primary-400">
                <x-heroicon-o-users class="w-6 h-6" />
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Peserta</p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $stats['total_peserta'] }}</p>
            </div>
        </x-filament::card>

        <x-filament::card class="flex items-center">
            <div class="p-3 bg-success-100 text-success-600 rounded-lg dark:bg-success-500/20 dark:text-success-400">
                <x-heroicon-o-check-badge class="w-6 h-6" />
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Tersedia (Siap Kerja)</p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $stats['tersedia'] }}</p>
            </div>
        </x-filament::card>

        <x-filament::card class="flex items-center">
            <div class="p-3 bg-info-100 text-info-600 rounded-lg dark:bg-info-500/20 dark:text-info-400">
                <x-heroicon-o-academic-cap class="w-6 h-6" />
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Program Pelatihan</p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $stats['total_pelatihan'] }}</p>
            </div>
        </x-filament::card>
    </div>

    {{-- Layout: Quick Actions & Recent Data --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        {{-- Recent Data Table --}}
        <div class="lg:col-span-2">
            <x-filament::card>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Data Peserta Terbaru</h3>
                    <x-filament::button tag="a" href="{{ \App\Filament\Client\Resources\PesertaResource::getUrl() }}" color="gray" size="sm">
                        Lihat Semua
                    </x-filament::button>
                </div>
                
                @if($recentPeserta->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="text-xs text-gray-500 uppercase bg-gray-50 dark:bg-gray-800 dark:text-gray-400">
                                <tr>
                                    <th class="px-4 py-3 rounded-tl-lg">Nama</th>
                                    <th class="px-4 py-3">Email</th>
                                    <th class="px-4 py-3">Tersedia</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach($recentPeserta as $peserta)
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $peserta->name }}</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $peserta->email }}</td>
                                        <td class="px-4 py-3">
                                            @if(optional($peserta->userProfile)->hire_status === '1')
                                                <span class="inline-flex items-center justify-center px-2 py-0.5 text-xs font-medium rounded-full bg-success-100 text-success-700 dark:bg-success-500/20 dark:text-success-400">
                                                    Ya
                                                </span>
                                            @else
                                                <span class="inline-flex items-center justify-center px-2 py-0.5 text-xs font-medium rounded-full bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400">
                                                    Tidak
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center py-8 text-gray-500">
                        <x-heroicon-o-inbox class="w-12 h-12 mb-3 text-gray-400" />
                        <p>Belum ada data peserta yang ditambahkan.</p>
                    </div>
                @endif
            </x-filament::card>
        </div>

        {{-- Quick Actions & System Info --}}
        <div class="space-y-6">
            <x-filament::card>
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Aksi Cepat</h3>
                <div class="flex flex-col gap-3">
                    <x-filament::button tag="a" href="{{ \App\Filament\Client\Resources\PesertaResource::getUrl('create') }}" icon="heroicon-o-user-plus" class="w-full justify-start">
                        Tambah Peserta Manual
                    </x-filament::button>
                    
                    <x-filament::button tag="a" href="{{ \App\Filament\Client\Pages\ImportPeserta::getUrl() }}" color="success" icon="heroicon-o-arrow-up-tray" class="w-full justify-start">
                        Import Data (Excel/Word)
                    </x-filament::button>
                </div>
            </x-filament::card>

            <x-filament::card class="bg-primary-50 border-primary-100 dark:bg-primary-900/10 dark:border-primary-800">
                <div class="flex items-start gap-3">
                    <x-heroicon-o-information-circle class="w-5 h-5 text-primary-600 dark:text-primary-400 mt-0.5" />
                    <div>
                        <h4 class="text-sm font-semibold text-primary-900 dark:text-primary-300 mb-1">Informasi Sistem</h4>
                        <p class="text-xs text-primary-700 dark:text-primary-400 leading-relaxed">
                            Pastikan data peserta selalu diperbarui untuk memaksimalkan peluang mereka mendapatkan pekerjaan. Gunakan fitur Import untuk menghemat waktu saat memasukkan data dalam jumlah besar.
                        </p>
                    </div>
                </div>
            </x-filament::card>
        </div>
    </div>
</x-filament-panels::page>
