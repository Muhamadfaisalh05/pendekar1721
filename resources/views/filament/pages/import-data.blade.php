<x-filament-panels::page>
    <div class="flex flex-wrap gap-4 mb-6">
        <x-filament::button wire:click="downloadExcelTemplate" color="success" icon="heroicon-o-document-arrow-down">
            Download Template Excel
        </x-filament::button>
        
        <x-filament::button wire:click="downloadWordTemplate" color="info" icon="heroicon-o-document-text">
            Download Template Word
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
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <x-filament::card>
                    <div class="text-sm font-medium text-gray-500">Total Data</div>
                    <div class="text-2xl font-bold">{{ $previewData['total'] }}</div>
                </x-filament::card>
                <x-filament::card>
                    <div class="text-sm font-medium text-gray-500">Data Valid</div>
                    <div class="text-2xl font-bold text-success-600">{{ count($previewData['valid']) }}</div>
                </x-filament::card>
                <x-filament::card>
                    <div class="text-sm font-medium text-gray-500">Data Error</div>
                    <div class="text-2xl font-bold text-danger-600">{{ count($previewData['errors']) }}</div>
                </x-filament::card>
                <x-filament::card>
                    <div class="text-sm font-medium text-gray-500">Data Duplikat</div>
                    <div class="text-2xl font-bold text-warning-600">{{ count($previewData['duplicates']) }}</div>
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
                                @foreach($previewData['errors'] as $error)
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
                                @foreach($previewData['duplicates'] as $dup)
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

            {{-- Valid Data Preview --}}
            @if(count($previewData['valid']) > 0)
                <x-filament::card>
                    <h3 class="text-lg font-bold mb-4">Preview Data (Siap Import)</h3>
                    <div class="overflow-x-auto max-h-96">
                        <table class="w-full text-sm text-left">
                            <thead class="text-xs text-gray-700 bg-gray-50 sticky top-0">
                                <tr>
                                    <th class="px-4 py-3">No</th>
                                    <th class="px-4 py-3">Nama</th>
                                    <th class="px-4 py-3">Email</th>
                                    <th class="px-4 py-3">JK</th>
                                    <th class="px-4 py-3">Kota KTP</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3">Foto (Excel)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach(array_slice($previewData['valid'], 0, 50) as $index => $data)
                                    <tr class="border-b">
                                        <td class="px-4 py-3">{{ $index + 1 }}</td>
                                        <td class="px-4 py-3 font-medium">{{ $data['name'] }}</td>
                                        <td class="px-4 py-3">{{ $data['email'] }}</td>
                                        <td class="px-4 py-3">{{ $data['gender'] }}</td>
                                        <td class="px-4 py-3">{{ \App\Models\MasterCity::find($data['ktp_city_id'])?->title ?? '-' }}</td>
                                        <td class="px-4 py-3">
                                            @if($data['hire_status'] === '1')
                                                <span class="text-success-600">Tersedia</span>
                                            @else
                                                <span class="text-gray-500">Tidak Tersedia</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-gray-500">{{ $data['photo_filename'] ?: '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @if(count($previewData['valid']) > 50)
                            <div class="p-4 text-center text-sm text-gray-500 bg-gray-50">
                                Menampilkan 50 data pertama dari {{ count($previewData['valid']) }} data valid.
                            </div>
                        @endif
                    </div>
                </x-filament::card>
            @endif

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
                                @foreach($previewData['tautan']['rencana'] as $rencana)
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
                </x-filament::card>
            @endif

            {{-- Penautan ke Data Anak (mode binaan) --}}
            @if(count($previewData['tautan']['akan'] ?? []) > 0 || count($previewData['tautan']['tidak'] ?? []) > 0)
                <x-filament::card>
                    <h3 class="text-lg font-bold mb-2">Penautan ke Data Anak</h3>
                    <p class="text-sm text-gray-600 mb-4">
                        Daftar ini menunjukkan baris yang akan ditautkan ke data anak binaan <strong>bila</strong> opsi
                        <strong>&quot;Tautkan ke anak yang sudah ada (jangan buat anak baru)&quot;</strong> Anda centang.
                        Hanya nama yang unik, anak belum punya akun, dan asal daerah/angkatan tidak bertentangan yang
                        ditautkan &mdash; nama kembar tidak pernah ditautkan otomatis.
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
                                    @foreach($previewData['tautan']['akan'] as $tautan)
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
                                @foreach($previewData['tautan']['tidak'] as $tautan)
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

            {{-- Kemungkinan Duplikat (nama + asal daerah + angkatan) --}}
            @if(count($previewData['kemungkinan_duplikat'] ?? []) > 0)
                <x-filament::card>
                    <div class="flex items-center gap-2 text-warning-600 mb-4">
                        <x-filament::icon icon="heroicon-o-exclamation-triangle" class="w-6 h-6" />
                        <h3 class="text-lg font-bold">Kemungkinan Duplikat</h3>
                    </div>
                    <p class="text-sm text-gray-600 mb-4">
                        Baris berikut mirip data yang sudah ada atau mirip baris lain di file (nama + asal daerah +
                        angkatan). Ini <strong>hanya peringatan</strong>: datanya tetap diimpor. Periksa kembali sebelum
                        menekan tombol Import.
                    </p>
                    <div class="overflow-x-auto max-h-72">
                        <table class="w-full text-sm text-left">
                            <thead class="text-xs text-gray-700 bg-gray-50 sticky top-0">
                                <tr>
                                    <th class="px-4 py-3">Baris</th>
                                    <th class="px-4 py-3">Nama</th>
                                    <th class="px-4 py-3">Email</th>
                                    <th class="px-4 py-3">Sumber</th>
                                    <th class="px-4 py-3">Keyakinan</th>
                                    <th class="px-4 py-3">Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($previewData['kemungkinan_duplikat'] as $duplikat)
                                    <tr class="border-b">
                                        <td class="px-4 py-3">{{ $duplikat['row'] ?? '-' }}</td>
                                        <td class="px-4 py-3 font-medium">{{ $duplikat['nama'] ?? '-' }}</td>
                                        <td class="px-4 py-3">{{ $duplikat['email'] ?? '-' }}</td>
                                        <td class="px-4 py-3">{{ $duplikat['jenis'] ?? '-' }}</td>
                                        <td class="px-4 py-3">{{ $duplikat['keyakinan'] ?? '-' }}</td>
                                        <td class="px-4 py-3 text-gray-600">{{ $duplikat['keterangan'] ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
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
            {{-- Hasil Import (ringkasan + baris gagal) --}}
            @if(is_array($hasilImport))
                <x-filament::card>
                    <h3 class="text-lg font-bold mb-2">Hasil Import</h3>
                    <p class="text-sm text-gray-600 mb-4">
                        {{ $hasilImport['akun_dibuat'] }} akun dibuat
                        ({{ $hasilImport['anak_baru'] }} anak baru,
                        {{ $hasilImport['ditautkan'] }} akun ditautkan),
                        {{ $hasilImport['gagal'] }} baris gagal.
                    </p>

                    @if(count($hasilImport['gagal_detail'] ?? []) > 0)
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left">
                                <thead class="text-xs text-gray-700 bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3">Baris / Blok</th>
                                        <th class="px-4 py-3">Nama</th>
                                        <th class="px-4 py-3">Alasan Gagal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($hasilImport['gagal_detail'] as $gagal)
                                        <tr class="border-b">
                                            <td class="px-4 py-3">{{ ! empty($gagal['blok']) ? 'Blok '.$gagal['blok'] : ($gagal['row'] ?? '-') }}</td>
                                            <td class="px-4 py-3 font-medium">{{ $gagal['nama'] ?? '-' }}</td>
                                            <td class="px-4 py-3 text-danger-600">{{ $gagal['alasan'] ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-filament::card>
            @endif
        </div>
</x-filament-panels::page>
