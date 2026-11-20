@extends('absensi.layout')

@section('title', 'Rapor')
@section('page', 'rapor')

@section('content')
    <div class="semarak-card">
        <div class="mb-6 flex flex-col justify-between gap-4 print:hidden lg:flex-row lg:items-center">
            <h2 class="flex items-center gap-2 text-xl font-bold text-slate-700">
                <svg class="h-5 w-5 text-blue-500" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="1.7" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                </svg>
                Cetak Dokumen Raport
            </h2>
            <div class="flex flex-wrap gap-2">
                <select id="reportAnak" class="rounded border border-slate-300 bg-white px-3 py-1.5 text-sm">
                    @foreach ($anakList as $anak)
                        <option value="{{ $anak->id }}">{{ $anak->nama }}</option>
                    @endforeach
                </select>
                <select id="reportPeriode" class="rounded border border-slate-300 bg-white px-3 py-1.5 text-sm">
                    <option value="harian">Raport Harian</option>
                    <option value="mingguan">Raport Mingguan</option>
                    <option value="bulanan">Raport Bulanan</option>
                    <option value="custom">Rentang Tanggal</option>
                </select>
                <input id="reportMulai" type="date" aria-label="Tanggal mulai rapor"
                    class="hidden rounded border border-slate-300 bg-white px-3 py-1.5 text-sm">
                <input id="reportSampai" type="date" aria-label="Tanggal akhir rapor"
                    class="hidden rounded border border-slate-300 bg-white px-3 py-1.5 text-sm">
                <button type="button" id="btnSimpanCatatan"
                    class="inline-flex items-center gap-1 rounded bg-teal-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-teal-700">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                        aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M17 21v-8H7v8M7 3v5h8M5.25 3h11.5A2.25 2.25 0 0 1 19 5.25v13.5A2.25 2.25 0 0 1 16.75 21H5.25A2.25 2.25 0 0 1 3 18.75V5.25A2.25 2.25 0 0 1 5.25 3Z" />
                    </svg>
                    Simpan Catatan
                </button>
                <button type="button" id="btnCetak"
                    class="inline-flex items-center gap-2 rounded bg-blue-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-700">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                        aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" />
                    </svg>
                    Cetak
                </button>
            </div>
        </div>

        <div id="reportMessage" class="mb-4 hidden rounded-lg p-3 text-sm print:hidden" role="status" aria-live="polite"></div>

        <article id="reportDocument" class="mx-auto max-w-4xl rounded-lg border border-slate-200 bg-white p-6 shadow-sm md:p-10">
            <header class="mb-8 border-b-2 border-black pb-4 text-center">
                <h3 class="text-lg font-bold">PEMERINTAH PROVINSI JAWA BARAT</h3>
                <h2 class="text-xl font-bold">DINAS SOSIAL</h2>
                <h4 class="font-bold">UPTD PUSAT PELAYANAN SOSIAL GRIYA BINA REMAJA</h4>
                <p class="text-xs">Jalan Maribaya No. 22 Lembang, Bandung Barat, Jawa Barat 40391</p>
                <p class="text-xs">Telepon. (022) 2786120</p>
                <p class="text-xs">Website: www.dinsos.ppsgbr.jabarprov.go.id Pos-el uptdppsgbr@gmail.com</p>
            </header>

            <div class="mb-6 flex items-center justify-between rounded border border-blue-200 bg-blue-50 p-3">
                <span class="text-sm font-bold tracking-wide">EVALUASI KARAKTER ANAK ASUH</span>
                <span class="rounded bg-blue-600 px-3 py-1 text-xs font-bold text-white">RAPORT PILAR KARAKTER</span>
            </div>

            <div class="mb-6 grid gap-4 text-sm md:grid-cols-2">
                <div id="reportIdentity"></div>
                <div id="reportPeriod"></div>
            </div>

            <div id="reportScores" class="mb-6 grid grid-cols-2 gap-3 text-center md:grid-cols-5"></div>

            <div class="mb-6 overflow-hidden rounded-lg border border-slate-200">
                <div class="border-b border-slate-200 bg-slate-50 px-4 py-2">
                    <h4 class="text-sm font-bold text-slate-700">REKAPITULASI PER KEGIATAN</h4>
                </div>
                <div class="responsive-table-wrap overflow-x-auto">
                    <table class="responsive-data-table report-table w-full text-left text-xs">
                        <colgroup>
                            <col class="report-col-no">
                            <col>
                            <col class="report-col-count">
                            <col class="report-col-count">
                            <col class="report-col-count">
                            <col class="report-col-count">
                        </colgroup>
                        <thead class="border-b border-slate-200 bg-white">
                            <tr>
                                <th class="px-3 py-2 text-center">No</th>
                                <th class="px-3 py-2">Kegiatan</th>
                                <th class="px-3 py-2 text-center">Sesuai</th>
                                <th class="px-3 py-2 text-center">Arahan</th>
                                <th class="px-3 py-2 text-center">Telat</th>
                                <th class="px-3 py-2 text-center">Tidak</th>
                            </tr>
                        </thead>
                        <tbody id="reportRows"></tbody>
                    </table>
                </div>
            </div>

            <div class="overflow-hidden rounded-lg border border-slate-200">
                <div class="border-b border-slate-200 bg-slate-50 px-4 py-2">
                    <h4 class="flex items-center gap-2 text-sm font-bold text-slate-700">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 0 1 .865-.501 48.172 48.172 0 0 0 3.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
                        </svg>
                        CATATAN &amp; REKOMENDASI
                    </h4>
                </div>
                <div class="grid grid-cols-1 gap-6 p-4 md:grid-cols-2">
                    <div>
                        <label for="catatanPerilaku" class="mb-2 block text-sm font-medium">Catatan Perilaku:</label>
                        <textarea id="catatanPerilaku"
                            class="min-h-32 w-full rounded border border-slate-200 p-3 text-sm print:resize-none print:border-0 print:p-0"></textarea>
                    </div>
                    <div>
                        <label for="rekomendasi" class="mb-2 block text-sm font-medium">Rekomendasi:</label>
                        <textarea id="rekomendasi"
                            class="min-h-32 w-full rounded border border-slate-200 p-3 text-sm print:resize-none print:border-0 print:p-0"></textarea>
                    </div>
                </div>
            </div>
        </article>
    </div>
@endsection
