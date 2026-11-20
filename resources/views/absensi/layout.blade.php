<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#5b4ce2">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="absensi-api" content="{{ route('absensi.api') }}">

    <title>@yield('title', 'SEMARAK') — Absensi | PENDEKAR1721</title>

    @vite(['resources/sass/app.scss', 'resources/js/app.js', 'resources/js/absensi.js'])
</head>

<body class="app-body flex min-h-screen flex-col text-slate-800 antialiased" data-absensi-page="@yield('page')">

    <header class="absensi-header sticky top-0 z-50 print:hidden">
        <div class="mx-auto flex w-full max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-3">
            <a href="{{ route('absensi.dashboard') }}" class="absensi-brand flex min-w-0 items-center">
                <span class="min-w-0">
                    <span class="flex items-center gap-2 text-xl font-bold leading-tight">
                        SEMARAK
                        <span class="absensi-brand-badge">UPTD PPSGBR</span>
                    </span>
                    <span class="absensi-brand-subtitle block truncate text-xs">
                        Sistem Monitoring &amp; Raport Karakter Anak
                    </span>
                </span>
            </a>

            <div class="flex items-center gap-2 md:gap-3">
                <a href="{{ route('absensi.input') }}" class="absensi-header-action" aria-label="Input evaluasi"
                    title="Input evaluasi">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m-7-7h14" />
                    </svg>
                    <span>Input Evaluasi</span>
                </a>
                @if ($__env->getSection('page') === 'dashboard')
                    <button id="dashboardFilterToggle" type="button" class="absensi-header-filter" aria-expanded="false"
                        aria-controls="dashboardFilterPanel">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                            aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                        </svg>
                        <span>Filter</span>
                    </button>
                @endif
                <a href="{{ route('home') }}" class="absensi-header-link">PENDEKAR1721</a>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="absensi-logout">
                        Keluar
                    </button>
                </form>
            </div>
        </div>
        @if ($__env->getSection('page') === 'dashboard')
            <div class="dashboard-filter-anchor">
                <section id="dashboardFilterPanel" class="dashboard-filter-popover hidden"
                    aria-label="Filter ringkasan perkembangan">
                    <div class="dashboard-filter-heading">
                        <span class="font-semibold text-slate-800">Filter ringkasan</span>
                        <button id="resetDashboardFilters" type="button"
                            class="inline-flex items-center gap-1 text-sm font-semibold text-indigo-600 hover:text-indigo-800">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" />
                            </svg>
                            Reset
                        </button>
                    </div>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <label class="text-xs font-semibold text-slate-600">Angkatan
                            <select id="filterAngkatan" class="dashboard-filter-control">
                                <option value="">Semua Angkatan</option>
                                @foreach ($angkatanList ?? [] as $angkatan)
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
                    </div>
                </section>
            </div>
        @endif
    </header>

    <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-6">
        @yield('content')
    </main>

    <footer class="border-t border-slate-200 py-5 text-center text-xs text-slate-500 print:hidden">
        SEMARAK — UPTD Pusat Pelayanan Sosial Griya Bina Remaja · terintegrasi dengan PENDEKAR1721
    </footer>

</body>

</html>

