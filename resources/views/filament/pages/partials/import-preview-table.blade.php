@php
    $paginator = $this->barisPreview;
    $labelStatus = [
        'valid' => 'Valid',
        'peringatan' => 'Peringatan',
        'error' => 'Error',
        'dilewati' => 'Dilewati',
    ];
    $kelasStatus = [
        'valid' => 'text-success-700 bg-success-50',
        'peringatan' => 'text-warning-700 bg-warning-50',
        'error' => 'text-danger-700 bg-danger-50',
        'dilewati' => 'text-gray-700 bg-gray-100',
    ];
    $gayaMasalah = 'background-color:#fee2e2;';
@endphp

<x-filament::card>
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4 mb-4">
        <div>
            <h3 class="text-lg font-bold">Tabel Preview Data</h3>
            <p class="text-sm text-gray-600">
                Semua baris file ditampilkan di sini. Baris bertanda CONTOH otomatis dilewati
                dan tidak diimpor; sel merah muda menandai nilai yang bermasalah.
            </p>
        </div>
        <div class="flex flex-col sm:flex-row gap-3 w-full lg:w-auto">
            <label class="text-sm">
                <span class="block text-gray-600 mb-1">Cari nama</span>
                <input
                    type="search"
                    wire:model.live.debounce.300ms="previewSearch"
                    placeholder="Ketik nama..."
                    class="w-full sm:w-56 rounded-lg border-gray-300 text-sm"
                >
            </label>
            <label class="text-sm">
                <span class="block text-gray-600 mb-1">Filter status</span>
                <select wire:model.live="previewStatus" class="w-full sm:w-44 rounded-lg border-gray-300 text-sm">
                    <option value="semua">Semua</option>
                    <option value="valid">Valid</option>
                    <option value="peringatan">Peringatan</option>
                    <option value="error">Error</option>
                    <option value="dilewati">Dilewati</option>
                </select>
            </label>
        </div>
    </div>

    <div class="text-xs text-gray-600 mb-2">
        Menampilkan {{ $paginator->firstItem() ?? 0 }}-{{ $paginator->lastItem() ?? 0 }}
        dari {{ $paginator->total() }} baris sesuai filter.
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left" style="min-width: 1800px;">
            <thead class="text-xs text-gray-700 bg-gray-50">
                <tr>
                    <th class="px-3 py-3">No / Blok</th>
                    <th class="px-3 py-3">Nama</th>
                    <th class="px-3 py-3">Jenis Kelamin</th>
                    <th class="px-3 py-3">Asal Daerah</th>
                    <th class="px-3 py-3">Agama</th>
                    <th class="px-3 py-3">Umur</th>
                    <th class="px-3 py-3">Tanggal Lahir</th>
                    <th class="px-3 py-3">Angkatan</th>
                    <th class="px-3 py-3">Email</th>
                    <th class="px-3 py-3">Alamat KTP</th>
                    <th class="px-3 py-3">Kota KTP</th>
                    <th class="px-3 py-3">Pendidikan</th>
                    <th class="px-3 py-3">Pelatihan</th>
                    <th class="px-3 py-3">Penempatan Wilayah</th>
                    <th class="px-3 py-3">Status Pekerjaan</th>
                    <th class="px-3 py-3">Status Baris</th>
                    <th class="px-3 py-3">Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @forelse($paginator as $baris)
                    @php
                        $status = $baris['status_baris'] ?? 'valid';
                        $masalah = $baris['masalah'] ?? [];
                        $gaya = fn (string $kunci): string => isset($masalah[$kunci]) ? $gayaMasalah : '';
                        $tip = fn (string $kunci): string => implode('; ', $masalah[$kunci] ?? []);
                    @endphp
                    <tr class="border-b align-top">
                        <td class="px-3 py-3 font-medium">
                            {{ ! empty($baris['blok']) ? 'Blok '.$baris['blok'] : ($baris['row'] ?? '-') }}
                        </td>
                        <td class="px-3 py-3 font-medium" style="{{ $gaya('nama') }}" title="{{ $tip('nama') }}">{{ $baris['nama'] ?: '-' }}</td>
                        <td class="px-3 py-3" style="{{ $gaya('jenis_kelamin') }}" title="{{ $tip('jenis_kelamin') }}">{{ $baris['jenis_kelamin'] ?: '-' }}</td>
                        <td class="px-3 py-3" style="{{ $gaya('asal_daerah') }}" title="{{ $tip('asal_daerah') }}">{{ $baris['asal_daerah'] ?: '-' }}</td>
                        <td class="px-3 py-3" style="{{ $gaya('agama') }}" title="{{ $tip('agama') }}">{{ $baris['agama'] ?: '-' }}</td>
                        <td class="px-3 py-3" style="{{ $gaya('umur') }}" title="{{ $tip('umur') }}">{{ $baris['umur'] ?: '-' }}</td>
                        <td class="px-3 py-3" style="{{ $gaya('tanggal_lahir') }}" title="{{ $tip('tanggal_lahir') }}">{{ $baris['tanggal_lahir'] ?: '-' }}</td>
                        <td class="px-3 py-3" style="{{ $gaya('angkatan') }}" title="{{ $tip('angkatan') }}">{{ $baris['angkatan'] ?: '-' }}</td>
                        <td class="px-3 py-3" style="{{ $gaya('email') }}" title="{{ $tip('email') }}">{{ $baris['email'] ?: '-' }}</td>
                        <td class="px-3 py-3" style="{{ $gaya('alamat_ktp') }}" title="{{ $tip('alamat_ktp') }}">{{ $baris['alamat_ktp'] ?: '-' }}</td>
                        <td class="px-3 py-3" style="{{ $gaya('kota_ktp') }}" title="{{ $tip('kota_ktp') }}">{{ $baris['kota_ktp'] ?: '-' }}</td>
                        <td class="px-3 py-3" style="{{ $gaya('pendidikan') }}" title="{{ $tip('pendidikan') }}">{{ $baris['pendidikan'] ?: '-' }}</td>
                        <td class="px-3 py-3" style="{{ $gaya('pelatihan') }}" title="{{ $tip('pelatihan') }}">{{ $baris['pelatihan'] ?: '-' }}</td>
                        <td class="px-3 py-3" style="{{ $gaya('penempatan_wilayah') }}" title="{{ $tip('penempatan_wilayah') }}">{{ $baris['penempatan_wilayah'] ?: '-' }}</td>
                        <td class="px-3 py-3" style="{{ $gaya('status_pekerjaan') }}" title="{{ $tip('status_pekerjaan') }}">{{ $baris['status_pekerjaan'] ?: '-' }}</td>
                        <td class="px-3 py-3">
                            <span class="inline-flex px-2 py-1 rounded-full text-xs font-semibold {{ $kelasStatus[$status] ?? 'text-gray-700 bg-gray-100' }}">
                                {{ $labelStatus[$status] ?? $status }}
                            </span>
                        </td>
                        <td class="px-3 py-3 text-gray-600">{{ $baris['keterangan'] ?: '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="17" class="px-3 py-6 text-center text-gray-500">
                            Tidak ada baris yang cocok dengan filter/pencarian.
                        </td>
                    </tr>
                @endforelse

            </tbody>
        </table>
    </div>

    @if($paginator->hasPages())
        <div class="flex flex-wrap items-center justify-between gap-3 mt-4">
            <div class="text-sm text-gray-600">
                Halaman {{ $paginator->currentPage() }} dari {{ $paginator->lastPage() }}
            </div>
            <div class="flex gap-2">
                <x-filament::button
                    size="sm"
                    color="gray"
                    wire:click="previousPreviewPage"
                    :disabled="$paginator->onFirstPage()"
                >
                    Sebelumnya
                </x-filament::button>
                <x-filament::button
                    size="sm"
                    color="gray"
                    wire:click="nextPreviewPage"
                    :disabled="! $paginator->hasMorePages()"
                >
                    Berikutnya
                </x-filament::button>
            </div>
        </div>
    @endif
</x-filament::card>

