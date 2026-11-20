@extends('absensi.layout')

@section('title', 'Input Evaluasi')
@section('page', 'input')

@section('content')
    <div class="semarak-card">
        <div class="mb-6 flex flex-col justify-between gap-4 md:flex-row md:items-center">
            <h2 class="flex items-center gap-2 text-xl font-bold text-slate-700">
                <svg class="h-5 w-5 text-blue-500" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="1.7" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                Input Evaluasi Karakter
            </h2>
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
                    <p class="flex items-center gap-2 text-sm font-medium text-yellow-800">
                        <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                        </svg>
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
                                <button type="button" data-bulk="sesuai"
                                    class="rounded border border-green-200 bg-green-100 px-3 py-1.5 text-green-700 hover:bg-green-200">Sesuai</button>
                                <button type="button" data-bulk="arahan"
                                    class="rounded border border-yellow-200 bg-yellow-100 px-3 py-1.5 text-yellow-700 hover:bg-yellow-200">Arahan</button>
                                <button type="button" data-bulk="telat"
                                    class="rounded border border-orange-200 bg-orange-100 px-3 py-1.5 text-orange-700 hover:bg-orange-200">Telat</button>
                                <button type="button" data-bulk="tidak"
                                    class="rounded border border-red-200 bg-red-100 px-3 py-1.5 text-red-700 hover:bg-red-200">Tidak</button>
                            </div>

                            <div class="grid gap-4 bg-slate-50/50 p-4 md:grid-cols-2 lg:grid-cols-3">
                                @foreach ($anakList as $anak)
                                    <div class="rounded-md border border-slate-200 bg-white p-3 shadow-sm"
                                        data-child="{{ $anak->id }}">
                                        <p class="mb-3 truncate text-sm font-medium text-slate-700">{{ $anak->nama }}</p>
                                        <span data-value-label
                                            class="mb-2 hidden inline-flex items-center rounded-full bg-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-700"></span>
                                        <div data-eval-options class="grid grid-cols-2 gap-2 text-xs font-medium">
                                            <button type="button" data-value="sesuai"
                                                class="eval-button rounded border border-slate-200 py-1.5 text-slate-600 hover:bg-green-100">Sesuai</button>
                                            <button type="button" data-value="arahan"
                                                class="eval-button rounded border border-slate-200 py-1.5 text-slate-600 hover:bg-yellow-100">Arahan</button>
                                            <button type="button" data-value="telat"
                                                class="eval-button rounded border border-slate-200 py-1.5 text-slate-600 hover:bg-orange-100">Telat</button>
                                            <button type="button" data-value="tidak"
                                                class="eval-button rounded border border-slate-200 py-1.5 text-slate-600 hover:bg-red-100">Tidak</button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="border-t border-slate-200 bg-slate-50 px-4 py-3 text-right">
                                <button type="button" data-save
                                    class="inline-flex items-center gap-1 rounded bg-teal-600 px-6 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-teal-700">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                    Simpan Penilaian
                                </button>
                            </div>
                        </div>
                    </section>
                @endforeach
            </div>
        @endif
    </div>
@endsection
