@php
    $detailHasil = $hasilImport['hasil_detail'] ?? [];
    $ringkasanHasil = $hasilImport['hasil_ringkasan'] ?? [
        'total' => count($detailHasil),
        'berhasil' => (int) ($hasilImport['akun_dibuat'] ?? 0),
        'dilewati' => (int) ($hasilImport['dilewati'] ?? 0),
        'gagal' => (int) ($hasilImport['gagal'] ?? 0),
    ];
    $kelasHasil = [
        'Berhasil' => 'text-success-700 bg-success-50',
        'Dilewati' => 'text-gray-700 bg-gray-100',
        'Gagal' => 'text-danger-700 bg-danger-50',
    ];
@endphp

<x-filament::card>
    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4 mb-4">
        <div>
            <h3 class="text-lg font-bold">Hasil Import</h3>
            <p class="text-sm text-gray-600">
                Total {{ $ringkasanHasil['total'] }} baris:
                <strong class="text-success-600">{{ $ringkasanHasil['berhasil'] }} berhasil</strong>,
                <strong class="text-gray-600">{{ $ringkasanHasil['dilewati'] }} dilewati</strong>,
                <strong class="text-danger-600">{{ $ringkasanHasil['gagal'] }} gagal</strong>.
                {{ $hasilImport['akun_dibuat'] ?? 0 }} akun dibuat
                ({{ $hasilImport['anak_baru'] ?? 0 }} anak baru,
                {{ $hasilImport['ditautkan'] ?? 0 }} akun ditautkan).
            </p>
        </div>
        @if(count($detailHasil) > 0)
            <x-filament::button
                wire:click="downloadLaporanHasil"
                color="gray"
                icon="heroicon-o-arrow-down-tray"
            >
                Unduh laporan hasil
            </x-filament::button>
        @endif
    </div>

    @if(count($detailHasil) > 0)
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left" style="min-width: 900px;">
                <thead class="text-xs text-gray-700 bg-gray-50">
                    <tr>
                        <th class="px-4 py-3">No / Blok</th>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">ID Anak</th>
                        <th class="px-4 py-3">Status Akun</th>
                        <th class="px-4 py-3">Hasil</th>
                        <th class="px-4 py-3">Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($detailHasil as $hasil)
                        <tr class="border-b align-top">
                            <td class="px-4 py-3 font-medium">
                                {{ ! empty($hasil['blok']) ? 'Blok '.$hasil['blok'] : ($hasil['row'] ?? '-') }}
                            </td>
                            <td class="px-4 py-3 font-medium">{{ $hasil['nama'] ?: '-' }}</td>
                            <td class="px-4 py-3">
                                {{ $hasil['anak_id'] ?: '-' }}
                                @if($hasil['anak_id'])
                                    <a
                                        href="{{ \App\Filament\Resources\AnakResource::getUrl('index') }}"
                                        class="block text-xs text-primary-600 underline"
                                    >
                                        Lihat di Anak Binaan
                                    </a>
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $hasil['status_akun'] ?: '-' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex px-2 py-1 rounded-full text-xs font-semibold {{ $kelasHasil[$hasil['hasil']] ?? 'text-gray-700 bg-gray-100' }}">
                                    {{ $hasil['hasil'] }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $hasil['keterangan'] ?: '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-xs text-gray-500 mt-3">
            Laporan unduhan tidak memuat email/alamat KTP. Nama dan ID anak dipertahankan
            agar operator dapat mencocokkan hasil dengan data binaan.
        </p>
    @elseif(count($hasilImport['gagal_detail'] ?? []) > 0)
        {{-- Kompatibilitas data lama yang belum memiliki hasil_detail. --}}
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs text-gray-700 bg-gray-50">
                    <tr>
                        <th class="px-4 py-3">Baris / Blok</th>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Hasil</th>
                        <th class="px-4 py-3">Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($hasilImport['gagal_detail'] as $gagal)
                        <tr class="border-b">
                            <td class="px-4 py-3">{{ ! empty($gagal['blok']) ? 'Blok '.$gagal['blok'] : ($gagal['row'] ?? '-') }}</td>
                            <td class="px-4 py-3 font-medium">{{ $gagal['nama'] ?? '-' }}</td>
                            <td class="px-4 py-3 text-danger-600">Gagal</td>
                            <td class="px-4 py-3 text-danger-600">{{ $gagal['alasan'] ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-filament::card>

