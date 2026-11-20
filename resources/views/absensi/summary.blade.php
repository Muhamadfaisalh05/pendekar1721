@extends('absensi.layout')

@section('title', 'Summary Individu')
@section('page', 'summary')

@section('content')
    <div class="semarak-card">
        <div class="mb-6 flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
            <h2 class="flex items-center gap-2 text-xl font-bold text-slate-700">
                <svg class="h-5 w-5 text-blue-500" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="1.7" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                </svg>
                Summary &amp; Analisis Individu
            </h2>
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
                <h4 id="detailHeading" class="flex items-center gap-2 text-sm font-bold text-slate-700">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                        aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                    </svg>
                    REKAPITULASI RINCI PER KEGIATAN
                </h4>
            </div>
            <div class="responsive-table-wrap overflow-x-auto">
                <table class="responsive-data-table w-full text-left text-sm">
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
