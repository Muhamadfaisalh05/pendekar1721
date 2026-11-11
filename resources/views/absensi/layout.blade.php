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
                    <span class="hidden sm:inline">Input Evaluasi</span>
                </a>
                <a href="{{ route('home') }}" class="absensi-header-link">PENDEKAR1721</a>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="absensi-logout">
                        Keluar
                    </button>
                </form>
            </div>
        </div>
    </header>

    <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-6">
        @yield('content')
    </main>

    <footer class="border-t border-slate-200 py-5 text-center text-xs text-slate-500 print:hidden">
        SEMARAK — UPTD Pusat Pelayanan Sosial Griya Bina Remaja · terintegrasi dengan PENDEKAR1721
    </footer>

</body>

</html>

