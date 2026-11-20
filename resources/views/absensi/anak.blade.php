@extends('absensi.layout')

@section('title', 'Data Anak')
@section('page', 'anak')

@section('content')
    <div class="semarak-card p-6">
        <div class="mb-6 flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h2 class="flex items-center gap-2 text-xl font-bold text-slate-700">
                    <svg class="h-5 w-5 text-blue-500" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="1.7" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z" />
                    </svg>
                    Data Anak
                </h2>
                <p class="mt-1 text-sm text-slate-500">Kelola data santri / anak asuh</p>
            </div>
            <button type="button" id="btnTambah"
                class="inline-flex items-center gap-2 rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m-7-7h14" />
                </svg>
                Input Anak
            </button>
        </div>

        <div id="modalAnak" class="child-modal hidden" role="dialog" aria-modal="true"
            aria-labelledby="modalAnakTitle">
            <div class="child-modal-backdrop" data-close-modal></div>
            <div class="child-modal-content">
                <div class="mb-4 flex items-center justify-between gap-4">
                    <h3 id="modalAnakTitle" class="text-lg font-bold text-slate-800">Input Anak</h3>
                    <button type="button" class="child-modal-close" aria-label="Tutup form" data-close-modal>
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form id="formAnak" class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                    <input type="hidden" id="anakId" name="id">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700" for="idAnak">ID Anak</label>
                            <input type="text" id="idAnak" name="id_manual" placeholder="Kosongkan untuk auto-generate"
                                class="w-full rounded border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700" for="nama">Nama</label>
                            <input type="text" id="nama" name="nama" required
                                class="w-full rounded border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700" for="jenisKelamin">Jenis
                                Kelamin</label>
                            <select id="jenisKelamin" name="jenis_kelamin" required
                                class="w-full rounded border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500">
                                <option value="">Pilih</option>
                                <option value="Laki-laki">Laki-laki</option>
                                <option value="Perempuan">Perempuan</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700" for="asalDaerah">Asal
                                Daerah</label>
                            <input type="text" id="asalDaerah" name="asal_daerah" required
                                class="w-full rounded border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700" for="agama">Agama</label>
                            <input type="text" id="agama" name="agama" required
                                class="w-full rounded border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700" for="umur">Umur</label>
                            <input type="number" id="umur" name="umur" min="1" required
                                class="w-full rounded border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700" for="angkatan">Angkatan</label>
                            <input type="text" id="angkatan" name="angkatan" required
                                class="w-full rounded border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700" for="status">Status</label>
                            <select id="status" name="status" required
                                class="w-full rounded border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500">
                                <option value="Aktif">Aktif</option>
                                <option value="Non-Aktif">Non-Aktif</option>
                                <option value="Lulus">Lulus</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-5 flex justify-end gap-3">
                        <button type="button" id="btnBatal"
                            class="rounded border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">Batal</button>
                        <button type="submit" id="btnSubmit"
                            class="inline-flex items-center gap-2 rounded bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M17 21v-8H7v8M7 3v5h8M5.25 3h11.5A2.25 2.25 0 0 1 19 5.25v13.5A2.25 2.25 0 0 1 16.75 21H5.25A2.25 2.25 0 0 1 3 18.75V5.25A2.25 2.25 0 0 1 5.25 3Z" />
                            </svg>
                            Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>


        <div class="overflow-hidden rounded-lg border border-slate-200">
            <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
                <h3 class="flex items-center gap-2 text-sm font-bold text-slate-700">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                        aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                    </svg>
                    Daftar Anak
                </h3>
            </div>
            <div class="child-table-wrapper overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-white text-slate-600">
                        <tr>
                            <th class="px-4 py-3">ID</th>
                            <th class="px-4 py-3">Nama</th>
                            <th class="px-4 py-3">Jenis Kelamin</th>
                            <th class="px-4 py-3">Asal Daerah</th>
                            <th class="px-4 py-3">Agama</th>
                            <th class="px-4 py-3">Umur</th>
                            <th class="px-4 py-3">Angkatan</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyAnak" class="bg-white"></tbody>
                </table>
            </div>
            <div id="mobileCards" class="child-mobile-list" aria-live="polite"></div>
        </div>
    </div>
@endsection

