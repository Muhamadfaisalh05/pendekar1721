@extends('absensi.layout')

@section('title', 'Pengaturan')
@section('page', 'pengaturan')

@section('content')
    <div class="semarak-card mb-6">
        <div class="mb-6 flex flex-col justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h2 class="flex items-center gap-2 text-lg font-bold text-slate-700">
                    <svg class="h-5 w-5 text-orange-500" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="1.7" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5m-9-6h.008v.008H12v-.008ZM12 15h.008v.008H12V15Zm0 2.25h.008v.008H12v-.008ZM9.75 15h.008v.008H9.75V15Zm0 2.25h.008v.008H9.75v-.008ZM7.5 15h.008v.008H7.5V15Zm0 2.25h.008v.008H7.5v-.008Zm6.75-4.5h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V15Zm0 2.25h.008v.008h-.008v-.008Zm2.25-4.5h.008v.008H16.5v-.008Zm0 2.25h.008v.008H16.5V15Z" />
                    </svg>
                    Pengaturan Jadwal Kegiatan
                </h2>
                <p class="mt-1 text-sm text-slate-500">Atur hari atau rentang tanggal aktif untuk setiap kegiatan.</p>
            </div>
            <button id="simpanJadwal" type="button"
                class="inline-flex items-center gap-2 rounded bg-orange-500 px-6 py-2 font-medium text-white shadow-sm hover:bg-orange-600">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                    aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M17 21v-8H7v8M7 3v5h8M5.25 3h11.5A2.25 2.25 0 0 1 19 5.25v13.5A2.25 2.25 0 0 1 16.75 21H5.25A2.25 2.25 0 0 1 3 18.75V5.25A2.25 2.25 0 0 1 5.25 3Z" />
                </svg>
                Simpan Jadwal
            </button>
        </div>

        <div id="pengaturanMessage" class="mb-4 hidden rounded-lg p-3 text-sm" role="status" aria-live="polite"></div>

        <div class="responsive-table-wrap rounded-lg border border-slate-200">
            <table class="responsive-data-table w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-600">
                    <tr>
                        <th class="w-12 px-4 py-3 text-center">No</th>
                        <th class="px-4 py-3">Uraian Kegiatan</th>
                        <th class="min-w-80 px-4 py-3">Aturan Jadwal Aktif</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($kegiatanList as $kegiatan)
                        <tr data-schedule="{{ $kegiatan->id }}"
                            class="border-b border-slate-100 align-top hover:bg-slate-50/50">
                            <td data-label="No" class="px-4 py-3 text-center align-top text-slate-500">{{ $loop->iteration }}</td>
                            <td data-label="Uraian kegiatan" class="px-4 py-3 align-top font-medium text-slate-700">{{ $kegiatan->nama }}</td>
                            <td data-label="Aturan jadwal aktif" class="px-4 py-3">
                                <select data-rule class="w-full rounded border border-slate-300 bg-white px-2 py-1.5 text-sm">
                                    <option value="setiap_hari">Setiap Hari</option>
                                    <option value="hari_kerja">Hari Kerja (Senin-Jumat)</option>
                                    <option value="multi_rentang">Beberapa Rentang (Multi)</option>
                                    <option value="hari_tertentu">Hari Tertentu</option>
                                </select>

                                <div data-days class="mt-3 hidden rounded border border-slate-200 bg-white p-3">
                                    <div class="flex flex-wrap gap-3 text-xs text-slate-600">
                                        @foreach (['1' => 'Senin', '2' => 'Selasa', '3' => 'Rabu', '4' => 'Kamis', '5' => 'Jumat', '6' => 'Sabtu', '7' => 'Minggu'] as $day => $name)
                                            <label class="flex items-center gap-1">
                                                <input type="checkbox" data-day="{{ $day }}" class="rounded">
                                                {{ $name }}
                                            </label>
                                        @endforeach
                                    </div>
                                </div>

                                <div data-ranges class="mt-3 hidden rounded border border-slate-200 bg-white p-3">
                                    <div data-range-list class="space-y-2"></div>
                                    <button type="button" data-add-range
                                        class="mt-3 w-full rounded border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs text-blue-600 hover:bg-blue-100">
                                        + Tambah Rentang
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="semarak-card">
        <div class="mb-6 flex flex-col justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h2 class="flex items-center gap-2 text-lg font-bold text-slate-700">
                    <svg class="h-5 w-5 text-purple-500" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="1.7" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.813m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5" />
                    </svg>
                    Pengaturan Masa Pembinaan
                </h2>
                <p class="mt-1 text-sm text-slate-500">Tanggal selesai tersimpan per angkatan.</p>
            </div>
            <button id="simpanPembinaan" type="button"
                class="rounded bg-blue-600 px-6 py-2 font-medium text-white shadow-sm hover:bg-blue-700">Simpan &amp; Terapkan Aturan</button>
        </div>

        <div class="responsive-table-wrap rounded-lg border border-slate-200">
            <table class="responsive-data-table w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-600">
                    <tr>
                        <th class="px-4 py-3">Angkatan</th>
                        <th class="px-4 py-3">Total Anak</th>
                        <th class="px-4 py-3">Tanggal Selesai Pembinaan</th>
                    </tr>
                </thead>
                <tbody id="cohortRows">
                    @foreach ($angkatanList as $angkatan)
                        <tr data-cohort="{{ $angkatan }}" class="border-b border-slate-100">
                            <td data-label="Angkatan" class="px-4 py-3 font-medium">{{ $angkatan }}</td>
                            <td data-label="Total anak" class="px-4 py-3">
                                {{ $anakList->where('angkatan', $angkatan)->count() }} anak
                            </td>
                            <td data-label="Tanggal selesai pembinaan" class="px-4 py-3">
                                <input data-end-date type="date"
                                    class="rounded border border-slate-300 bg-white px-2 py-1.5 text-sm">
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
