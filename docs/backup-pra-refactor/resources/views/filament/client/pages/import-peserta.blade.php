<x-filament-panels::page>
    <div class="flex flex-wrap gap-4 mb-6">
        <x-filament::button wire:click="downloadExcelTemplate" color="success" icon="heroicon-o-document-arrow-down">
            Download Template Excel
        </x-filament::button>
        
        <x-filament::button wire:click="downloadWordTemplate" color="info" icon="heroicon-o-document-text">
            Download Template Word
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
                                    <th class="px-4 py-3">Baris</th>
                                    <th class="px-4 py-3">Nama</th>
                                    <th class="px-4 py-3">Kolom</th>
                                    <th class="px-4 py-3">Data</th>
                                    <th class="px-4 py-3">Masalah</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($previewData['errors'] as $error)
                                    <tr class="border-b">
                                        <td class="px-4 py-3 font-medium">{{ $error['row'] }}</td>
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
                                    <th class="px-4 py-3">Baris</th>
                                    <th class="px-4 py-3">Nama</th>
                                    <th class="px-4 py-3">Email</th>
                                    <th class="px-4 py-3">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($previewData['duplicates'] as $dup)
                                    <tr class="border-b">
                                        <td class="px-4 py-3 font-medium">{{ $dup['row'] }}</td>
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
</x-filament-panels::page>
