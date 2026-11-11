@extends('absensi.layout')

@section('title', 'Input Evaluasi')
@section('page', 'input')

@section('content')
    <div class="semarak-card">
        <div class="mb-6 flex flex-col justify-between gap-4 md:flex-row md:items-center">
            <h2 class="text-xl font-bold text-slate-700">Input Evaluasi Karakter</h2>
            <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                Tanggal penilaian
                <input id="tanggalEvaluasi" type="date" value="{{ now()->toDateString() }}"
                    class="rounded border border-slate-300 bg-white px-3 py-1.5 outline-none focus:border-blue-500">
            </label>
        </div>

        <div class="mb-6 rounded-lg border border-blue-100 bg-blue-50 p-4 text-sm text-blue-800">
            Pilih satu nilai untuk setiap anak pada masing-masing kegiatan, lalu tekan
            <strong>Simpan Penilaian</strong>. Nilai yang sudah tersimpan akan dimuat kembali saat tanggal dipilih.
        </div>

        @if ($anakList->isEmpty())
            <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-800">
                Belum ada data anak. Tambahkan data terlebih dahulu melalui menu Data Anak.
            </div>
        @elseif ($kegiatanList->isEmpty())
            <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-800">
                Belum ada kegiatan yang dapat dinilai.
            </div>
        @else
            <div class="mb-6 rounded-lg border border-yellow-200 bg-yellow-50 p-4">
                <div class="mb-3 flex flex-col justify-between gap-3 md:flex-row md:items-center">
                    <p class="text-sm font-medium text-yellow-800">
                        Tandai anak sakit / izin untuk seluruh kegiatan pada tanggal ini
                    </p>
                    <button type="button" id="simpanLibur"
                        class="rounded bg-yellow-600 px-4 py-2 text-sm font-medium text-white hover:bg-yellow-700">
                        Simpan Sakit / Izin
                    </button>
                </div>
                <div class="flex flex-wrap gap-3">
                    @foreach ($anakList as $anak)
                        <label class="flex cursor-pointer items-center gap-2 rounded border border-yellow-200 bg-white px-3 py-1.5 text-sm">
                            <input type="checkbox" data-leave-child="{{ $anak->id }}" class="rounded text-yellow-600">
                            {{ $anak->nama }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div id="pesanEvaluasi" class="mb-4 hidden rounded-lg p-3 text-sm" role="status" aria-live="polite"></div>

            <div class="space-y-3">
                @foreach ($kegiatanList as $kegiatan)
                    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white"
                        data-activity="{{ $kegiatan->id }}">
                        <button type="button" data-accordion
                            class="flex w-full items-center justify-between border-b border-slate-200 bg-slate-100 px-4 py-3 text-left transition hover:bg-slate-200">
                            <span class="flex items-center gap-2 font-bold text-slate-700">
                                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-blue-600 text-xs text-white">{{ $loop->iteration }}</span>
                                {{ $kegiatan->nama }}
                            </span>
                            <span class="flex items-center gap-4">
                                <span id="status-{{ $kegiatan->id }}"
                                    class="rounded bg-slate-200 px-2 py-0.5 text-xs text-slate-600">Belum disimpan</span>
                                <svg data-accordion-icon class="h-4 w-4 text-slate-400 transition-transform"
                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                                </svg>
                            </span>
                        </button>

                        <div data-accordion-content class="hidden">
                            <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 bg-slate-50 px-4 py-2 text-sm">
                                <span class="mr-2 font-medium text-slate-600">Set semua anak:</span>
                                @foreach (['sesuai' => 'Sesuai', 'arahan' => 'Arahan', 'telat' => 'Telat', 'tidak' => 'Tidak'] as $value => $label)
                                    <button type="button" data-bulk="{{ $value }}"
                                        class="rounded border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-100">{{ $label }}</button>
                                @endforeach
                                <button type="button" data-save
                                    class="ml-auto rounded bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Simpan Penilaian</button>
                            </div>

                            <div class="grid gap-4 bg-slate-50/50 p-4 md:grid-cols-2 lg:grid-cols-3">
                                @foreach ($anakList as $anak)
                                    <div class="rounded-md border border-slate-200 bg-white p-3 shadow-sm"
                                        data-child="{{ $anak->id }}">
                                        <p class="mb-3 truncate text-sm font-medium text-slate-700">{{ $anak->nama }}</p>
                                        <span data-value-label class="mb-2 hidden text-xs text-slate-500"></span>
                                        <div class="grid grid-cols-2 gap-2 text-xs font-medium">
                                            @foreach (['sesuai' => 'Sesuai', 'arahan' => 'Arahan', 'telat' => 'Telat', 'tidak' => 'Tidak'] as $value => $label)
                                                <button type="button" data-value="{{ $value }}"
                                                    class="eval-button rounded border border-slate-200 py-1.5 text-slate-600 hover:bg-slate-100">{{ $label }}</button>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </section>
                @endforeach
            </div>
        @endif
    </div>
@endsection
