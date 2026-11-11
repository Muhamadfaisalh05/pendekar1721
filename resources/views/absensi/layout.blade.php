<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#083668">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="absensi-api" content="{{ route('absensi.api') }}">

    <title>@yield('title', 'SEMARAK') — Absensi | PENDEKAR1721</title>

    @vite(['resources/sass/app.scss', 'resources/js/app.js', 'resources/js/absensi.js'])
</head>

<body class="flex min-h-screen flex-col bg-slate-50 text-slate-800 antialiased"
    data-absensi-page="@yield('page')">

    <header class="absensi-header sticky top-0 z-50 print:hidden">
        <div class="mx-auto flex w-full max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-3">
            <a href="{{ route('absensi.dashboard') }}" class="flex min-w-0 items-center gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/15 text-lg font-black text-white">
                    S
                </span>
                <span class="min-w-0">
                    <span class="flex items-center gap-2 text-lg font-bold leading-tight text-white">
                        SEMARAK
                        <span class="rounded-full bg-white/20 px-2 py-0.5 text-[10px] font-normal uppercase tracking-wide">UPTD PPSGBR</span>
                    </span>
                    <span class="block truncate text-xs text-blue-100">Sistem Monitoring &amp; Raport Karakter Anak</span>
                </span>
            </a>

            <div class="flex items-center gap-2">
                <a href="{{ route('absensi.input') }}" class="absensi-header-action">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m-7-7h14" />
                    </svg>
                    <span class="hidden sm:inline">Input Evaluasi</span>
                </a>
                <a href="{{ route('home') }}" class="absensi-header-link">PENDEKAR1721</a>

                <div class="flex items-center gap-2 rounded-full bg-white/10 py-1 pl-3 pr-1">
                    <span class="hidden text-xs text-blue-100 sm:inline">
                        {{ auth()->user()?->name }} · {{ auth()->user()?->roleLabel() }}
                    </span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                            class="rounded-full bg-white/15 px-3 py-1 text-xs font-semibold text-white transition hover:bg-white/25">
                            Keluar
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <nav class="border-t border-white/10 bg-[#062d57]/60" aria-label="Navigasi modul absensi">
            <div class="mx-auto flex w-full max-w-7xl gap-1 overflow-x-auto px-2 text-sm">
                @foreach ([
        'absensi.dashboard' => 'Dasbor',
        'absensi.input' => 'Input Evaluasi',
        'absensi.anak' => 'Data Anak',
        'absensi.summary' => 'Summary',
        'absensi.rapor' => 'Rapor',
        'absensi.pengaturan' => 'Pengaturan',
    ] as $routeName => $label)
                    <a href="{{ route($routeName) }}"
                        class="absensi-nav-link {{ request()->routeIs($routeName) ? 'is-active' : '' }}">{{ $label }}</a>
                @endforeach
            </div>
        </nav>
    </header>

    <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-6">
        @yield('content')
    </main>

    <footer class="border-t border-slate-200 py-5 text-center text-xs text-slate-500 print:hidden">
        SEMARAK — UPTD Pusat Pelayanan Sosial Griya Bina Remaja · terintegrasi dengan PENDEKAR1721
    </footer>

</body>

</html>
