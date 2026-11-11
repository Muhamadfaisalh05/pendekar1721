@extends('absensi.layout')

@section('title', 'Data Anak')
@section('page', 'anak')

@section('content')
    <div class="semarak-card mb-6">
        <h2 class="mb-4 text-xl font-bold text-slate-700">Form Data Anak</h2>

        <form id="formAnak" class="grid gap-3 md:grid-cols-2 lg:grid-cols-4">
            <label class="text-sm font-medium text-slate-700">ID Anak (opsional)
                <input id="anakId" type="text" placeholder="AN-007"
                    class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm">
            </label>
            <label class="text-sm font-medium text-slate-700">Nama
                <input id="nama" type="text" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm">
            </label>
            <label class="text-sm font-medium text-slate-700">Jenis kelamin
                <select id="jenis_kelamin" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm">
                    <option value="Laki-laki">Laki-laki</option>
                    <option value="Perempuan">Perempuan</option>
                </select>
            </label>
            <label class="text-sm font-medium text-slate-700">Asal daerah
                <input id="asal_daerah" type="text" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm">
            </label>
            <label class="text-sm font-medium text-slate-700">Agama
                <input id="agama" type="text" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm">
            </label>
            <label class="text-sm font-medium text-slate-700">Umur
                <input id="umur" type="number" min="1" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm">
            </label>
            <label class="text-sm font-medium text-slate-700">Angkatan
                <input id="angkatan" type="text" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm">
            </label>
            <label class="text-sm font-medium text-slate-700">Status
                <select id="status" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm">
                    <option value="Aktif">Aktif</option>
                    <option value="Non-Aktif">Non-Aktif</option>
                    <option value="Lulus">Lulus</option>
                </select>
            </label>

            <div class="md:col-span-2 lg:col-span-4 flex flex-wrap gap-2">
                <button type="submit" class="rounded bg-blue-600 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    Simpan
                </button>
                <button type="button" id="resetFormAnak"
                    class="rounded border border-slate-300 px-5 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">
                    Reset
                </button>
            </div>
        </form>

        <div id="anakMessage" class="mt-4 hidden rounded-lg p-3 text-sm" role="status" aria-live="polite"></div>
    </div>

    <div class="semarak-card">
        <h2 class="mb-4 text-xl font-bold text-slate-700">Daftar Anak Binaan</h2>
        <div class="responsive-table-wrap rounded-lg border border-slate-200">
            <table class="responsive-data-table text-left">
                <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-600">
                    <tr>
                        <th class="px-4 py-3">ID</th>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">JK</th>
                        <th class="px-4 py-3">Asal</th>
                        <th class="px-4 py-3">Agama</th>
                        <th class="px-4 py-3">Umur</th>
                        <th class="px-4 py-3">Angkatan</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody id="anakRows" class="divide-y divide-slate-100"></tbody>
            </table>
        </div>
    </div>
@endsection
