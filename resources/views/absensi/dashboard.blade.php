@extends('absensi.layout')

@section('title', 'Dasbor')
@section('page', 'dashboard')

@section('content')
    <section class="mb-6 rounded-2xl bg-gradient-to-br from-[#083668] via-[#0c4a82] to-[#1460a5] p-6 text-white shadow-lg md:p-8">
        <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
            <div class="max-w-2xl">
                <h1 class="text-2xl font-bold leading-tight md:text-3xl">Pantau perkembangan anak</h1>
                <p class="mt-2 text-sm text-white/85 md:text-base">Catat perkembangan karakter anak setiap hari.</p>
            </div>
            <div class="rounded-xl bg-white/10 px-4 py-3 text-sm">
                <time id="currentDate" datetime="{{ now()->toDateString() }}">{{ now()->translatedFormat('d F Y') }}</time>
                <div id="currentTime" class="mt-1 text-right font-mono text-lg">--:--:--</div>
            </div>
        </div>
    </section>

    <section class="mb-7">
        <h2 class="mb-3 font-bold text-slate-800">Menu utama</h2>
        <div class="portal-grid">
            @foreach ([
        ['absensi.anak', 'Data Anak', 'bg-blue-600'],
        ['absensi.summary', 'Summary Individu', 'bg-emerald-600'],
        ['absensi.rapor', 'Rapor', 'bg-orange-500'],
        ['absensi.pengaturan', 'Pengaturan', 'bg-slate-600'],
    ] as [$routeName, $label, $color])
                <a href="{{ route($routeName) }}" class="portal-card">
                    <span class="portal-icon {{ $color }}">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6" />
                        </svg>
                    </span>
                    <span class="min-w-0 flex-1 truncate font-semibold text-slate-700">{{ $label }}</span>
                </a>
            @endforeach
        </div>
    </section>

    <section class="semarak-card mb-6">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="font-bold text-slate-800">Hasil evaluasi</h2>
                <p class="mt-1 text-xs text-slate-500">Persentase dari penilaian yang masuk sesuai filter.</p>
            </div>
            <button type="button" id="dashboardFilterToggle"
                class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-semibold text-slate-600 hover:bg-slate-50"
                aria-expanded="false" aria-controls="dashboardFilterPanel">Filter</button>
        </div>

        <div id="dashboardFilterPanel" class="mb-4 hidden rounded-xl border border-slate-200 bg-slate-50 p-4">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <label class="text-xs font-semibold text-slate-600">Angkatan
                    <select id="filterAngkatan"
                        class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-normal">
                        <option value="">Semua Angkatan</option>
                        @foreach ($angkatanList as $angkatan)
                            <option value="{{ $angkatan }}">{{ $angkatan }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-xs font-semibold text-slate-600">Status anak
                    <select id="filterStatus"
                        class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-normal">
                        <option value="Aktif">Hanya Siswa Aktif</option>
                        <option value="">Semua Status</option>
                        <option value="Non-Aktif">Non-Aktif</option>
                        <option value="Lulus">Lulus</option>
                    </select>
                </label>
                <label class="text-xs font-semibold text-slate-600">Dari tanggal
                    <input id="filterMulai" type="date"
                        class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-normal">
                </label>
                <label class="text-xs font-semibold text-slate-600">Sampai tanggal
                    <input id="filterSampai" type="date"
                        class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-normal">
                </label>
                <div class="flex items-end">
                    <button type="button" id="resetDashboardFilters"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-white">Reset</button>
                </div>
            </div>
        </div>

    </section>

    <div id="dashboardMessage" class="mb-4 hidden rounded-lg p-3 text-sm" role="status" aria-live="polite"></div>

    <section class="semarak-card mb-6">
        <div class="grid gap-3 text-center sm:grid-cols-2 lg:grid-cols-5">
            @foreach ([
        ['statSesuai', 'SESUAI', 'border-emerald-200 bg-emerald-50 text-emerald-700'],
        ['statArahan', 'ARAHAN', 'border-amber-200 bg-amber-50 text-amber-700'],
        ['statTelat', 'TELAT', 'border-orange-200 bg-orange-50 text-orange-700'],
        ['statTidak', 'TIDAK', 'border-rose-200 bg-rose-50 text-rose-700'],
    ] as [$id, $label, $color])
                <div class="rounded-xl border py-4 {{ $color }}">
                    <p class="text-xs font-bold">{{ $label }}</p>
                    <p id="{{ $id }}" class="mt-1 text-2xl font-bold">0.0%</p>
                </div>
            @endforeach
            <div class="rounded-xl border border-blue-200 bg-blue-50 py-4 text-blue-700">
                <p class="text-xs font-bold">SKOR TOTAL</p>
                <p id="statSkor" class="mt-1 text-2xl font-bold">0.0%</p>
            </div>
        </div>

        <dl class="mt-4 grid grid-cols-3 gap-3 text-center text-sm">
            <div class="rounded-lg border border-slate-200 bg-white py-3">
                <dt class="text-xs font-semibold text-slate-500">Total Anak</dt>
                <dd id="totalAnak" class="text-xl font-bold text-slate-800">0</dd>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white py-3">
                <dt class="text-xs font-semibold text-slate-500">Total Kegiatan</dt>
                <dd id="totalKegiatan" class="text-xl font-bold text-slate-800">0</dd>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white py-3">
                <dt class="text-xs font-semibold text-slate-500">Total Penilaian</dt>
                <dd id="totalPenilaian" class="text-xl font-bold text-slate-800">0</dd>
            </div>
        </dl>
    </section>

    <section class="semarak-card">
        <h2 class="mb-3 font-bold text-slate-800">Tren penilaian harian</h2>
        <p id="chartEmpty" class="hidden rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-500">
            Belum ada penilaian pada rentang ini.
        </p>
        <div id="trendChart" class="space-y-2"></div>
    </section>
@endsection
