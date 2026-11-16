<x-filament-panels::page>
    <div class="flex flex-wrap gap-4 mb-6">
        <x-filament::button wire:click="downloadExcelTemplate" color="success" icon="heroicon-o-document-arrow-down">
            Download Template Excel
        </x-filament::button>

        <x-filament::button wire:click="downloadFormulirWordTemplate" color="warning" icon="heroicon-o-identification">
            Download Formulir Word (per anak)
        </x-filament::button>
    </div>

    <form wire:submit.prevent="processPreview">
        {{ $this->form }}

        <div class="mt-4">
            <x-filament::button type="submit" color="primary" icon="heroicon-o-eye" wire:loading.attr="disabled">
                Preview Data
            </x-filament::button>
            
            <div wire:loading wire:target="processPreview" class="ml-3 text-sm text-gray-500">
                Memproses file...
            </div>
        </div>
    </form>

    @if($previewData)
        <div class="mt-8 space-y-6">
            {{-- Summary Cards --}}
            @php
                $ringkasanPreview = $previewData['ringkasan'] ?? [
                    'total' => $previewData['total'] ?? 0,
                    'valid' => count($previewData['valid'] ?? []),
                    'peringatan' => 0,
                    'error' => count(array_unique(array_column($previewData['errors'] ?? [], 'row'))),
                    'dilewati' => 0,
                ];
            @endphp
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
                <x-filament::card>
                    <div class="text-sm font-medium text-gray-500">Total Baris</div>
                    <div class="text-2xl font-bold">{{ $ringkasanPreview['total'] }}</div>
                </x-filament::card>
                <x-filament::card>
                    <div class="text-sm font-medium text-gray-500">Valid</div>
                    <div class="text-2xl font-bold text-success-600">{{ $ringkasanPreview['valid'] }}</div>
                </x-filament::card>
                <x-filament::card>
                    <div class="text-sm font-medium text-gray-500">Peringatan</div>
                    <div class="text-2xl font-bold text-warning-600">{{ $ringkasanPreview['peringatan'] }}</div>
                </x-filament::card>
                <x-filament::card>
                    <div class="text-sm font-medium text-gray-500">Error</div>
                    <div class="text-2xl font-bold text-danger-600">{{ $ringkasanPreview['error'] }}</div>
                </x-filament::card>
                <x-filament::card>
                    <div class="text-sm font-medium text-gray-500">Dilewati</div>
                    <div class="text-2xl font-bold text-gray-600">{{ $ringkasanPreview['dilewati'] }}</div>
                </x-filament::card>
            </div>

            {{-- Error Table --}}
            @if(count($previewData['errors']) > 0)
                <x-filament::card>
                    <div class="flex items-center gap-2 text-danger-600 mb-4">
                        <x-filament::icon icon="heroicon-o-exclamation-triangle" class="w-6 h-6" />
                        <h3 class="text-lg font-bold">Daftar Error Validasi</h3>
                    </div>
                    <p class="text-sm text-gray-600 mb-4">Terdapat baris yang tidak sesuai format atau data master. Perbaiki file dan upload ulang. Tombol Import dinonaktifkan.</p>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="text-xs text-gray-700 bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3">Baris / Blok</th>
                                    <th class="px-4 py-3">Nama</th>
                                    <th class="px-4 py-3">Kolom</th>
                                    <th class="px-4 py-3">Data</th>
                                    <th class="px-4 py-3">Masalah</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach(array_slice($previewData['errors'], 0, 100) as $error)
                                    <tr class="border-b">
                                        <td class="px-4 py-3 font-medium">{{ ! empty($error['blok']) ? 'Blok '.$error['blok'] : $error['row'] }}</td>
                                        <td class="px-4 py-3">{{ $error['name'] ?: '-' }}</td>
                                        <td class="px-4 py-3">{{ $error['col'] }}</td>
                                        <td class="px-4 py-3 text-danger-600">{{ $error['val'] ?: '(kosong)' }}</td>
                                        <td class="px-4 py-3">{{ $error['msg'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-filament::card>
            @endif

            {{-- Duplicates Table --}}
            @if(count($previewData['duplicates']) > 0)
                <x-filament::card>
                    <div class="flex items-center gap-2 text-warning-600 mb-4">
                        <x-filament::icon icon="heroicon-o-information-circle" class="w-6 h-6" />
                        <h3 class="text-lg font-bold">Data Duplikat (Akan Dilewati)</h3>
                    </div>
                    <p class="text-sm text-gray-600 mb-4">Data berikut sudah ada di sistem (berdasarkan Email) dan tidak akan diimport ulang.</p>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="text-xs text-gray-700 bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3">Baris / Blok</th>
                                    <th class="px-4 py-3">Nama</th>
                                    <th class="px-4 py-3">Email</th>
                                    <th class="px-4 py-3">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach(array_slice($previewData['duplicates'], 0, 25) as $dup)
                                    <tr class="border-b">
                                        <td class="px-4 py-3 font-medium">{{ ! empty($dup['blok']) ? 'Blok '.$dup['blok'] : $dup['row'] }}</td>
                                        <td class="px-4 py-3">{{ $dup['name'] }}</td>
                                        <td class="px-4 py-3">{{ $dup['email'] }}</td>
                                        <td class="px-4 py-3 text-warning-600">Data sudah ada</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-filament::card>
            @endif

            {{-- Tabel Preview Data: SEMUA baris, server-side pagination --}}
            @include('filament.pages.partials.import-preview-table')

            {{-- Rencana Import per Baris (mode binaan) --}}
            @if(count($previewData['tautan']['rencana'] ?? []) > 0)
                <x-filament::card>
                    <h3 class="text-lg font-bold mb-2">Rencana Import per Baris</h3>
                    <p class="text-sm text-gray-600 mb-4">
                        Setiap baris pada mode Remaja Binaan membuat <strong>data anak baru (status Aktif)</strong> sekaligus
                        <strong>akun Draft</strong> yang tertaut. Bila opsi
                        <strong>&quot;Tautkan ke anak yang sudah ada&quot;</strong> dicentang, baris yang cocok hanya dibuatkan
                        akun Draft (tanpa anak baru).
                    </p>
                    <div class="overflow-x-auto max-h-72">
                        <table class="w-full text-sm text-left">
                            <thead class="text-xs text-gray-700 bg-gray-50 sticky top-0">
                                <tr>
                                    <th class="px-4 py-3">Baris</th>
                                    <th class="px-4 py-3">Nama</th>
                                    <th class="px-4 py-3">Email</th>
                                    <th class="px-4 py-3">Rencana</th>
                                    <th class="px-4 py-3">Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach(array_slice($previewData['tautan']['rencana'], 0, 25) as $rencana)
                                    <tr class="border-b">
                                        <td class="px-4 py-3">{{ $rencana['row'] ?? '-' }}</td>
                                        <td class="px-4 py-3 font-medium">{{ $rencana['nama'] ?? '-' }}</td>
                                        <td class="px-4 py-3">{{ $rencana['email'] ?: '-' }}</td>
                                        <td class="px-4 py-3">
                                            @if(($rencana['aksi'] ?? '') === 'tautkan')
                                                <span class="text-info-600 font-semibold">Tautkan ke {{ $rencana['anak_id'] }}</span>
                                            @else
                                                <span class="text-success-600 font-semibold">Anak baru + akun Draft</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-gray-600">{{ $rencana['keterangan'] ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if(count($previewData['tautan']['rencana']) > 25)
                        <p class="text-xs text-gray-500 mt-2">
                            Menampilkan 25 rencana pertama. Seluruh baris (dengan status dan keterangan)
                            tersedia pada tabel Preview Data di atas.
                        </p>
                    @endif
                </x-filament::card>
            @endif

            {{-- Penautan ke Data Anak (mode binaan) --}}
            @if(count($previewData['tautan']['akan'] ?? []) > 0 || count($previewData['tautan']['tidak'] ?? []) > 0)
                <x-filament::card>
                    <h3 class="text-lg font-bold mb-2">Penautan ke Data Anak</h3>
                    <p class="text-sm text-gray-600 mb-4">
                        Daftar ini menunjukkan baris yang akan ditautkan ke data anak binaan <strong>bila</strong> opsi
                        <strong>&quot;Tautkan ke anak yang sudah ada (jangan buat anak baru)&quot;</strong> Anda centang.
                        Hanya baris yang identitasnya (nama + alamat KTP + tanggal lahir + asal daerah + angkatan)
                        tidak bertentangan dan menunjuk satu anak yang belum punya akun yang ditautkan &mdash; nama
                        sama tanpa alamat KTP pembeda tidak pernah ditautkan otomatis.
                    </p>

                    @if(count($previewData['tautan']['akan'] ?? []) > 0)
                        <div class="text-sm font-semibold text-success-600 mb-2">
                            Akan ditautkan ({{ count($previewData['tautan']['akan']) }})
                        </div>
                        <div class="overflow-x-auto max-h-72">
                            <table class="w-full text-sm text-left">
                                <thead class="text-xs text-gray-700 bg-gray-50 sticky top-0">
                                    <tr>
                                        <th class="px-4 py-3">Baris</th>
                                        <th class="px-4 py-3">Nama (file)</th>
                                        <th class="px-4 py-3">Email</th>
                                        <th class="px-4 py-3">Data Anak</th>
                                        <th class="px-4 py-3">Asal Daerah</th>
                                        <th class="px-4 py-3">Angkatan</th>
                                        <th class="px-4 py-3">Status Anak</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach(array_slice($previewData['tautan']['akan'], 0, 25) as $tautan)
                                        <tr class="border-b">
                                            <td class="px-4 py-3">{{ $tautan['row'] ?? '-' }}</td>
                                            <td class="px-4 py-3 font-medium">{{ $tautan['nama'] ?? '-' }}</td>
                                            <td class="px-4 py-3">{{ $tautan['email'] ?? '-' }}</td>
                                            <td class="px-4 py-3">{{ $tautan['anak_id'] ?? '-' }}</td>
                                            <td class="px-4 py-3">{{ $tautan['asal_daerah'] ?? '-' }}</td>
                                            <td class="px-4 py-3">{{ $tautan['angkatan'] ?? '-' }}</td>
                                            <td class="px-4 py-3">{{ $tautan['status'] ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-sm text-gray-500">Tidak ada akun yang dapat ditautkan otomatis dari file ini.</p>
                    @endif

                    @if(count($previewData['tautan']['tidak'] ?? []) > 0)
                        <div class="text-sm font-semibold text-warning-600 mt-6 mb-2">
                            Tidak dapat ditautkan otomatis ({{ count($previewData['tautan']['tidak']) }})
                        </div>
                        <table class="w-full text-sm text-left">
                            <thead class="text-xs text-gray-700 bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3">Baris</th>
                                    <th class="px-4 py-3">Nama (file)</th>
                                    <th class="px-4 py-3">Email</th>
                                    <th class="px-4 py-3">Alasan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach(array_slice($previewData['tautan']['tidak'], 0, 25) as $tautan)
                                    <tr class="border-b">
                                        <td class="px-4 py-3">{{ $tautan['row'] ?? '-' }}</td>
                                        <td class="px-4 py-3 font-medium">{{ $tautan['nama'] ?? '-' }}</td>
                                        <td class="px-4 py-3">{{ $tautan['email'] ?? '-' }}</td>
                                        <td class="px-4 py-3 text-gray-600">{{ $tautan['alasan'] ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </x-filament::card>
            @endif

            {{-- Identitas & Duplikat (mode binaan) --}}
            @php($duplikatBinaan = $previewData['duplikat'] ?? ['pasti' => [], 'kemungkinan' => []])

            @if(count($duplikatBinaan['pasti'] ?? []) > 0 || count($duplikatBinaan['kemungkinan'] ?? []) > 0)
                <x-filament::card>
                    <div class="flex items-center gap-2 text-warning-600 mb-4">
                        <x-filament::icon icon="heroicon-o-exclamation-triangle" class="w-6 h-6" />
                        <h3 class="text-lg font-bold">Duplikat &amp; Nama Sama</h3>
                    </div>
                    <p class="text-sm text-gray-600 mb-4">
                        Identitas dihitung dari <strong>nama + alamat KTP + tanggal lahir + asal daerah + angkatan</strong>.
                        Nama sama saja <strong>tidak</strong> dianggap duplikat, sehingga dua anak bernama sama tetap dapat
                        diimpor selama alamat/tanggal lahirnya berbeda.
                    </p>

                    @if(count($duplikatBinaan['pasti'] ?? []) > 0)
                        <div class="text-sm font-semibold text-danger-600 mb-2">
                            Ganda pasti ({{ count($duplikatBinaan['pasti']) }})
                        </div>
                        <p class="text-sm text-gray-600 mb-2">
                            @if($previewData['impor_ganda'] ?? false)
                                Opsi <strong>&quot;Impor tetap baris yang terdeteksi ganda&quot;</strong> aktif, jadi baris
                                berikut tetap diimpor.
                            @else
                                Baris berikut <strong>tidak akan diimpor</strong>. Centang
                                <strong>&quot;Impor tetap baris yang terdeteksi ganda&quot;</strong> lalu tekan Preview ulang
                                bila Anda yakin baris tersebut orang yang berbeda.
                            @endif
                        </p>
                        <div class="overflow-x-auto max-h-72 mb-6">
                            <table class="w-full text-sm text-left">
                                <thead class="text-xs text-gray-700 bg-gray-50 sticky top-0">
                                    <tr>
                                        <th class="px-4 py-3">Baris</th>
                                        <th class="px-4 py-3">Nama</th>
                                        <th class="px-4 py-3">Alamat KTP (file)</th>
                                        <th class="px-4 py-3">Sumber</th>
                                        <th class="px-4 py-3">Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach(array_slice($duplikatBinaan['pasti'], 0, 25) as $duplikat)
                                        <tr class="border-b">
                                            <td class="px-4 py-3">{{ $duplikat['row'] ?? '-' }}</td>
                                            <td class="px-4 py-3 font-medium">{{ $duplikat['nama'] ?? '-' }}</td>
                                            <td class="px-4 py-3">{{ ($duplikat['alamat'] ?? null) ?: '-' }}</td>
                                            <td class="px-4 py-3">{{ $duplikat['sumber'] ?? '-' }}</td>
                                            <td class="px-4 py-3 text-gray-600">{{ $duplikat['keterangan'] ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    @if(count($duplikatBinaan['kemungkinan'] ?? []) > 0)
                        <div class="text-sm font-semibold text-warning-600 mb-2">
                            Nama sama, identitas belum pasti ({{ count($duplikatBinaan['kemungkinan']) }})
                        </div>
                        <p class="text-sm text-gray-600 mb-2">
                            Ini <strong>hanya peringatan</strong>: baris tetap diimpor. Periksa manual bila alamat KTP atau
                            tanggal lahirnya ternyata sama.
                        </p>
                        <div class="overflow-x-auto max-h-72">
                            <table class="w-full text-sm text-left">
                                <thead class="text-xs text-gray-700 bg-gray-50 sticky top-0">
                                    <tr>
                                        <th class="px-4 py-3">Baris</th>
                                        <th class="px-4 py-3">Nama</th>
                                        <th class="px-4 py-3">Alamat KTP (file)</th>
                                        <th class="px-4 py-3">Jenis</th>
                                        <th class="px-4 py-3">Sumber</th>
                                        <th class="px-4 py-3">Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach(array_slice($duplikatBinaan['kemungkinan'], 0, 25) as $duplikat)
                                        <tr class="border-b">
                                            <td class="px-4 py-3">{{ $duplikat['row'] ?? '-' }}</td>
                                            <td class="px-4 py-3 font-medium">{{ $duplikat['nama'] ?? '-' }}</td>
                                            <td class="px-4 py-3">{{ ($duplikat['alamat'] ?? null) ?: '-' }}</td>
                                            <td class="px-4 py-3">{{ $duplikat['jenis'] ?? '-' }}</td>
                                            <td class="px-4 py-3">{{ $duplikat['sumber'] ?? '-' }}</td>
                                            <td class="px-4 py-3 text-gray-600">{{ $duplikat['keterangan'] ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-filament::card>
            @endif

            {{-- Import Action --}}
            <div class="flex justify-end mt-4">
                <x-filament::button 
                    wire:click="confirmImport" 
                    color="primary" 
                    size="lg"
                    icon="heroicon-o-check-circle"
                    :disabled="count($previewData['errors']) > 0 || count($previewData['valid']) === 0"
                >
                    Import Data Sekarang
                </x-filament::button>
            </div>
        </div>
    @endif

        <div class="mt-8">
            {{-- Hasil Import (tabel per baris + unduhan) --}}
            @if(is_array($hasilImport))
                @include('filament.pages.partials.import-result-table')
            @endif
        </div>
</x-filament-panels::page>
