@extends('layouts.base')

@section('body')
    <header class="site-header sticky top-0 z-50">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between gap-4 h-20">
                <a href="{{ route('home') }}" class="flex items-center gap-3 min-w-0">
                    <img src="{{ asset('images/dinsos-logo-01.png') }}" class="h-11 w-auto sm:h-12"
                        alt="Logo PENDEKAR1721" />
                    <div class="hidden sm:block min-w-0">
                        <div class="text-lg font-black tracking-[-0.06em] text-[#083668] leading-none">PENDEKAR1721</div>
                        <div class="mt-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-500">UPTD PPSGBR
                        </div>
                    </div>
                </a>

                <nav class="hidden lg:flex items-center gap-8 text-sm font-semibold text-slate-700">
                    <a href="{{ route('home') }}" class="nav-link active">Beranda</a>
                    <a href="#about" class="nav-link">Profil</a>
                    <a href="#program" class="nav-link">Program</a>
                    <a href="#data" class="nav-link">Data</a>
                    <a href="#statistik" class="nav-link">Statistik</a>
                    <a href="#informasi" class="nav-link">Informasi</a>
                </nav>

                <div class="hidden md:flex items-center gap-3">
                    <a href="{{ route('filament.client.auth.login') }}" class="btn btn-ghost">Masuk Klien</a>
                    <a href="{{ route('filament.admin.auth.login') }}" class="btn btn-primary">Admin</a>
                </div>

                <div class="lg:hidden" x-data="{ open: false }">
                    <button @click="open = !open" type="button" class="menu-toggle" aria-label="Toggle menu">
                        <svg x-show="!open" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        <svg x-cloak x-show="open" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>

                    <div x-cloak x-show="open" @click.away="open = false" x-transition class="mobile-menu">
                        <a href="{{ route('home') }}" class="mobile-link">Beranda</a>
                        <a href="#about" class="mobile-link">Profil</a>
                        <a href="#program" class="mobile-link">Program</a>
                        <a href="#data" class="mobile-link">Data</a>
                        <a href="#statistik" class="mobile-link">Statistik</a>
                        <a href="#informasi" class="mobile-link">Informasi</a>
                        <div class="mt-4 space-y-2 border-t border-slate-200 pt-4">
                            <a href="{{ route('filament.client.auth.login') }}" class="mobile-login">Masuk Klien</a>
                            <a href="{{ route('filament.admin.auth.login') }}" class="mobile-login secondary">Admin</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="flex-1">
        @yield('content')
    </main>

    <footer class="site-footer">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="grid gap-10 md:grid-cols-2 lg:grid-cols-3">
                <div class="lg:col-span-2">
                    <div class="flex items-center gap-4 mb-5">
                        <img src="{{ asset('images/dinsos-logo-01.png') }}" class="h-12 w-auto" alt="Logo PENDEKAR1721" />
                        <div>
                            <div class="text-xl font-black tracking-[-0.06em] text-white">PENDEKAR1721</div>
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-300">UPTD PPSGBR</div>
                        </div>
                    </div>
                    <p class="max-w-xl text-sm leading-7 text-slate-300">
                        Direktori profil tenaga kerja terampil yang telah mendapatkan pelatihan keterampilan dari UPTD Pusat
                        Pelayanan Sosial Griya Bina Remaja.
                    </p>
                </div>

                <div>
                    <h3 class="text-sm font-bold uppercase tracking-[0.2em] text-slate-300 mb-4">Navigasi</h3>
                    <ul class="space-y-3 text-sm text-slate-300">
                        <li><a href="{{ route('home') }}" class="footer-link">Beranda</a></li>
                        <li><a href="#about" class="footer-link">Profil</a></li>
                        <li><a href="#program" class="footer-link">Program</a></li>
                        <li><a href="#data" class="footer-link">Data</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="border-t border-white/10">
            <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-4 text-center text-xs text-slate-400">
                © {{ date('Y') }} PENDEKAR1721 — UPTD Pusat Pelayanan Sosial Griya Bina Remaja
            </div>
        </div>
    </footer>
@endsection