@extends('absensi.layout')

@section('title', 'Rapor')
@section('page', 'rapor')

@section('content')
    <div class="semarak-card">
        <div class="mb-6 flex flex-col justify-between gap-4 print:hidden lg:flex-row lg:items-center">
            <h2 class="text-xl font-bold text-slate-700">Cetak Dokumen Rapor</h2>
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
                    class="rounded bg-teal-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-teal-700">Simpan Catatan</button>
                <button type="button" id="btnCetak"
                    class="rounded bg-blue-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-700">Cetak</button>
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

            <div class="responsive-table-wrap mb-6 overflow-hidden rounded-lg border border-slate-200">
                <table class="responsive-data-table text-left">
                    <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-600">
                        <tr>
                            <th class="px-3 py-2">No</th>
                            <th class="px-3 py-2">Kegiatan</th>
                            <th class="px-3 py-2 text-center">Sesuai</th>
                            <th class="px-3 py-2 text-center">Arahan</th>
                            <th class="px-3 py-2 text-center">Telat</th>
                            <th class="px-3 py-2 text-center">Tidak</th>
                            <th class="px-3 py-2 text-center">Libur</th>
                        </tr>
                    </thead>
                    <tbody id="reportRows" class="divide-y divide-slate-100"></tbody>
                </table>
            </div>

            <div id="reportStandar" class="mb-6 flex flex-wrap gap-2 text-xs"></div>

            <div class="grid gap-4 text-sm md:grid-cols-2">
                <label class="font-medium text-slate-700">Catatan perilaku
                    <textarea id="catatanPerilaku" rows="4"
                        class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm"></textarea>
                </label>
                <label class="font-medium text-slate-700">Rekomendasi
                    <textarea id="rekomendasi" rows="4"
                        class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm"></textarea>
                </label>
            </div>
        </article>
    </div>
@endsection
