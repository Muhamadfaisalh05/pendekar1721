@extends('absensi.layout')

@section('title', 'Dasbor')
@section('page', 'dashboard')

@section('content')
    <section class="dashboard-hero mb-6 p-6 md:p-8">
        <div class="dashboard-hero-content">
            <div class="max-w-2xl">
                <h1 class="text-2xl font-bold leading-tight md:text-3xl">Pantau perkembangan anak</h1>
                <p class="mt-2 text-sm text-white/85 md:text-base">Catat perkembangan karakter anak setiap hari.</p>
            </div>
            <div class="dashboard-datetime" aria-label="Tanggal dan waktu saat ini">
                <div class="dashboard-current-date">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                        aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                    </svg>
                    <time id="currentDate" datetime="{{ now()->toDateString() }}">{{ now()->translatedFormat('d F Y') }}</time>
                </div>
                <time id="currentTime" class="dashboard-current-time" aria-label="Waktu saat ini">--:--:--</time>
            </div>
        </div>
    </section>

    <section class="mb-7" aria-labelledby="portalHeading">
        <div class="mb-3">
            <h2 id="portalHeading" class="font-bold text-slate-800">Menu utama</h2>
            <p class="mt-1 text-xs text-slate-500">Pilih layanan yang ingin Anda buka.</p>
        </div>

        <div class="portal-list">
            <a href="{{ route('absensi.anak') }}" class="portal-card">
                <span class="portal-card-icon portal-icon-blue">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                        aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                    </svg>
                </span>
                <span class="portal-card-copy">
                    <span class="portal-card-title">Data Anak</span>
                </span>
                <svg class="portal-card-arrow h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                </svg>
            </a>

            <a href="{{ route('absensi.summary') }}" class="portal-card">
                <span class="portal-card-icon portal-icon-green">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                        aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                    </svg>
                </span>
                <span class="portal-card-copy">
                    <span class="portal-card-title">Summary Individu</span>
                </span>
                <svg class="portal-card-arrow h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                </svg>
            </a>

            <a href="{{ route('absensi.rapor') }}" class="portal-card">
                <span class="portal-card-icon portal-icon-orange">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                        aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                </span>
                <span class="portal-card-copy">
                    <span class="portal-card-title">Rapor</span>
                </span>
                <svg class="portal-card-arrow h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                </svg>
            </a>

            <a href="{{ route('absensi.pengaturan') }}" class="portal-card">
                <span class="portal-card-icon portal-icon-slate">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                        aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                </span>
                <span class="portal-card-copy">
                    <span class="portal-card-title">Pengaturan</span>
                </span>
                <svg class="portal-card-arrow h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                </svg>
            </a>
        </div>
    </section>

    <div id="dashboardMessage" class="mb-4 hidden rounded-xl p-3 text-sm" role="status" aria-live="polite"></div>

    <section class="mb-6">
        <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="font-bold text-slate-800">Hasil evaluasi</h2>
                <p class="mt-1 text-xs text-slate-500">Persentase dari penilaian yang masuk sesuai filter.</p>
            </div>
            <div class="flex items-center gap-3">
                <span class="hidden items-center gap-2 text-xs text-slate-500 sm:inline-flex">
                    <span class="h-2 w-2 rounded-full bg-indigo-500"></span> Data diperbarui otomatis
                </span>
                <button type="button" id="dashboardFilterToggle" class="dashboard-filter-button"
                    aria-expanded="false" aria-controls="dashboardFilterPanel">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                        aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                    </svg>
                    Filter
                </button>
            </div>
        </div>

        <div id="dashboardFilterPanel" class="dashboard-filter-panel mb-4 hidden p-4">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <label class="text-xs font-semibold text-slate-600">Angkatan
                    <select id="filterAngkatan" class="dashboard-filter-control">
                        <option value="">Semua Angkatan</option>
                        @foreach ($angkatanList as $angkatan)
                            <option value="{{ $angkatan }}">{{ $angkatan }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-xs font-semibold text-slate-600">Status anak
                    <select id="filterStatus" class="dashboard-filter-control">
                        <option value="Aktif">Hanya Siswa Aktif</option>
                        <option value="">Semua Status</option>
                        <option value="Non-Aktif">Non-Aktif</option>
                        <option value="Lulus">Lulus</option>
                    </select>
                </label>
                <label class="text-xs font-semibold text-slate-600">Dari tanggal
                    <input id="filterMulai" type="date" class="dashboard-filter-control">
                </label>
                <label class="text-xs font-semibold text-slate-600">Sampai tanggal
                    <input id="filterSampai" type="date" class="dashboard-filter-control">
                </label>
                <div class="flex items-end">
                    <button type="button" id="resetDashboardFilters" class="dashboard-filter-button w-full justify-center">
                        Reset
                    </button>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 md:gap-4 xl:grid-cols-4">
            <article class="dashboard-stat p-4 md:p-5">
                <div class="mb-4 flex items-center justify-between">
                    <p class="text-xs font-semibold text-slate-600 md:text-sm">Sesuai</p>
                    <span class="stat-icon bg-green-50 text-green-600">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                    </span>
                </div>
                <p id="statSesuai" class="text-2xl font-bold text-slate-800 md:text-3xl">0.0%</p>
                <p class="mt-1 text-xs text-slate-400">Konsisten mengikuti kegiatan</p>
            </article>

            <article class="dashboard-stat p-4 md:p-5">
                <div class="mb-4 flex items-center justify-between">
                    <p class="text-xs font-semibold text-slate-600 md:text-sm">Dengan arahan</p>
                    <span class="stat-icon bg-amber-50 text-amber-600">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M10.05 4.575a1.575 1.575 0 1 0-3.15 0v3m3.15-3v-1.5a1.575 1.575 0 0 1 3.15 0v1.5m-3.15 0 .075 5.925m3.075.75V4.575m0 0a1.575 1.575 0 0 1 3.15 0V15M6.9 7.575a1.575 1.575 0 1 0-3.15 0v8.175a6.75 6.75 0 0 0 6.75 6.75h2.018a5.25 5.25 0 0 0 3.712-1.538l1.732-1.732a5.25 5.25 0 0 0 1.538-3.712l.003-2.024a.668.668 0 0 1 .198-.471 1.575 1.575 0 1 0-2.228-2.228 3.818 3.818 0 0 0-1.12 2.687M6.9 7.575V12m6.27 4.318A4.49 4.49 0 0 1 16.35 15" />
                        </svg>
                    </span>
                </div>
                <p id="statArahan" class="text-2xl font-bold text-slate-800 md:text-3xl">0.0%</p>
                <p class="mt-1 text-xs text-slate-400">Memerlukan pengingat</p>
            </article>

            <article class="dashboard-stat p-4 md:p-5">
                <div class="mb-4 flex items-center justify-between">
                    <p class="text-xs font-semibold text-slate-600 md:text-sm">Terlambat</p>
                    <span class="stat-icon bg-orange-50 text-orange-600">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </span>
                </div>
                <p id="statTelat" class="text-2xl font-bold text-slate-800 md:text-3xl">0.0%</p>
                <p class="mt-1 text-xs text-slate-400">Perlu pendampingan waktu</p>
            </article>

            <article class="dashboard-stat p-4 md:p-5">
                <div class="mb-4 flex items-center justify-between">
                    <p class="text-xs font-semibold text-slate-600 md:text-sm">Tidak dilakukan</p>
                    <span class="stat-icon bg-rose-50 text-rose-600">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                        </svg>
                    </span>
                </div>
                <p id="statTidak" class="text-2xl font-bold text-slate-800 md:text-3xl">0.0%</p>
                <p class="mt-1 text-xs text-slate-400">Perlu tindak lanjut</p>
            </article>
        </div>
    </section>

    <section class="grid grid-cols-1 gap-4 xl:grid-cols-[0.8fr_1.7fr]">
        <div class="grid grid-cols-3 gap-3 xl:grid-cols-1">
            <article class="dashboard-panel p-4">
                <p class="text-xs text-slate-500">Anak sesuai filter</p>
                <p id="totalAnak" class="mt-1 text-2xl font-bold text-slate-800">0</p>
            </article>
            <article class="dashboard-panel p-4">
                <p class="text-xs text-slate-500">Kegiatan terpantau</p>
                <p id="totalKegiatan" class="mt-1 text-2xl font-bold text-slate-800">0</p>
            </article>
            <article class="dashboard-panel p-4">
                <p class="text-xs text-slate-500">Penilaian tersimpan</p>
                <p id="totalPenilaian" class="mt-1 text-2xl font-bold text-slate-800">0</p>
            </article>
        </div>

        <div class="dashboard-panel p-4 md:p-5">
            <div class="mb-4">
                <h2 class="font-bold text-slate-800">Tren penilaian harian</h2>
                <p class="mt-1 text-xs text-slate-500">Jumlah evaluasi dan proporsi nilai sesuai per hari.</p>
            </div>
            <p id="chartEmpty" class="hidden rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-500">
                Belum ada penilaian pada rentang ini.
            </p>
            <div id="trendChart" class="space-y-3"></div>
        </div>
    </section>
@endsection
