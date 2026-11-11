@extends('absensi.layout')

@section('title', 'Summary Individu')
@section('page', 'summary')

@section('content')
    <div class="semarak-card">
        <div class="mb-6 flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
            <h2 class="text-xl font-bold text-slate-700">Summary &amp; Analisis Individu</h2>
            <div class="flex flex-wrap gap-2">
                <select id="pilihAnak" aria-label="Pilih anak"
                    class="rounded border border-slate-300 bg-white px-3 py-1.5 text-sm">
                    @foreach ($anakList as $anak)
                        <option value="{{ $anak->id }}">{{ $anak->nama }} ({{ $anak->status }})</option>
                    @endforeach
                </select>
                <input id="mulai" type="date" aria-label="Tanggal mulai"
                    class="rounded border border-slate-300 bg-white px-3 py-1.5 text-sm">
                <input id="sampai" type="date" aria-label="Tanggal selesai"
                    class="rounded border border-slate-300 bg-white px-3 py-1.5 text-sm">
                <button id="refreshSummary" type="button" aria-label="Muat ulang"
                    class="rounded border border-slate-300 bg-slate-100 px-3 py-1.5 hover:bg-slate-200">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v6h6M20 20v-6h-6M5.5 15a7 7 0 0 0 12 3M18.5 9a7 7 0 0 0-12-3" />
                    </svg>
                </button>
            </div>
        </div>

        <div id="summaryMessage" class="mb-4 hidden rounded-lg p-3 text-sm" role="status" aria-live="polite"></div>
        <div id="anakProfile" class="mb-6 flex flex-col items-start gap-6 rounded-lg border border-slate-200 p-6 md:flex-row md:items-center"></div>
        <div id="summaryCards" class="mb-6 grid grid-cols-2 gap-4 text-center lg:grid-cols-5"></div>

        <div class="overflow-hidden rounded-lg border border-slate-200">
            <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
                <h3 class="text-sm font-bold text-slate-700">REKAPITULASI RINCI PER KEGIATAN</h3>
            </div>
            <div class="responsive-table-wrap">
                <table class="responsive-data-table text-left">
                    <thead class="border-b border-slate-200 bg-white text-xs text-slate-500">
                        <tr>
                            <th class="px-4 py-3">No</th>
                            <th class="px-4 py-3">Kegiatan</th>
                            <th class="px-4 py-3 text-center">Sesuai</th>
                            <th class="px-4 py-3 text-center">Arahan</th>
                            <th class="px-4 py-3 text-center">Telat</th>
                            <th class="px-4 py-3 text-center">Tidak</th>
                            <th class="px-4 py-3 text-center">Libur</th>
                            <th class="px-4 py-3 text-center">Skor</th>
                        </tr>
                    </thead>
                    <tbody id="detailRows" class="divide-y divide-slate-100"></tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
