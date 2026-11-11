@extends('absensi.layout')

@section('title', 'Pengaturan')
@section('page', 'pengaturan')

@section('content')
    <div class="semarak-card mb-6">
        <div class="mb-6 flex flex-col justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h2 class="text-lg font-bold text-slate-700">Pengaturan Jadwal Kegiatan</h2>
                <p class="mt-1 text-sm text-slate-500">Atur hari atau rentang tanggal aktif untuk setiap kegiatan.</p>
            </div>
            <button id="simpanJadwal" type="button"
                class="rounded bg-orange-500 px-6 py-2 font-medium text-white shadow-sm hover:bg-orange-600">Simpan Jadwal</button>
        </div>

        <div id="pengaturanMessage" class="mb-4 hidden rounded-lg p-3 text-sm" role="status" aria-live="polite"></div>

        <div class="responsive-table-wrap rounded-lg border border-slate-200">
            <table class="responsive-data-table text-left">
                <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-600">
                    <tr>
                        <th class="w-12 px-4 py-3 text-center">No</th>
                        <th class="px-4 py-3">Uraian Kegiatan</th>
                        <th class="min-w-80 px-4 py-3">Aturan Jadwal Aktif</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($kegiatanList as $kegiatan)
                        <tr data-schedule="{{ $kegiatan->id }}" class="align-top hover:bg-slate-50/50">
                            <td class="px-4 py-3 text-center text-slate-500">{{ $loop->iteration }}</td>
                            <td class="px-4 py-3 font-medium text-slate-700">{{ $kegiatan->nama }}</td>
                            <td class="px-4 py-3">
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
                                        class="mt-3 rounded bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-100">
                                        Tambah Rentang
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
                <h2 class="text-lg font-bold text-slate-700">Masa Pembinaan</h2>
                <p class="mt-1 text-sm text-slate-500">Tentukan tanggal selesai pembinaan per angkatan.</p>
            </div>
            <button id="simpanPembinaan" type="button"
                class="rounded bg-orange-500 px-6 py-2 font-medium text-white shadow-sm hover:bg-orange-600">Simpan Masa Pembinaan</button>
        </div>

        <div class="responsive-table-wrap rounded-lg border border-slate-200">
            <table class="responsive-data-table text-left">
                <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-600">
                    <tr>
                        <th class="px-4 py-3">Angkatan</th>
                        <th class="px-4 py-3">Tanggal Selesai</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($angkatanList as $angkatan)
                        <tr data-cohort="{{ $angkatan }}">
                            <td class="px-4 py-3 font-medium text-slate-700">{{ $angkatan }}</td>
                            <td class="px-4 py-3">
                                <input data-end-date type="date"
                                    class="rounded border border-slate-300 bg-white px-3 py-1.5 text-sm">
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
