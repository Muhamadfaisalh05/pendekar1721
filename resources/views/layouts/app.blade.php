@extends('layouts.base')

@section('body')
    {{-- ============================================ --}}
    {{-- TOP HEADER — White, professional, clean      --}}
    {{-- ============================================ --}}
    <header class="bg-white border-b border-gray-200 relative z-50">
        <div class="container mx-auto px-4 sm:px-6">
            <div class="flex items-center justify-between py-4 gap-4">
                {{-- Left: Logo + Identity --}}
                <div class="flex items-center gap-4 min-w-0">
                    <a href="{{ route('home') }}" class="flex-shrink-0">
                        <img src="{{ asset('images/dinsos-logo-01.png') }}" class="h-12 sm:h-14 lg:h-16 w-auto" alt="Logo Dinas Sosial Provinsi Jawa Barat" />
                    </a>
                    <div class="hidden sm:block min-w-0">
                        <h1 class="text-lg lg:text-xl font-bold text-primary-900 tracking-tight leading-tight">PENDEKAR1721</h1>
                        <p class="text-xs lg:text-sm text-gray-500 leading-tight truncate">UPTD Pusat Pelayanan Sosial Griya Bina Remaja</p>
                    </div>
                </div>

                {{-- Right: Login Buttons (Desktop) --}}
                <div class="hidden md:flex items-center gap-3">
                    <a href="{{ route('filament.client.auth.login') }}"
                       class="inline-flex items-center gap-2 bg-primary-700 hover:bg-primary-800 text-white text-sm font-medium py-2.5 px-5 rounded-lg transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                        </svg>
                        Masuk Klien
                    </a>
                    <a href="{{ route('filament.admin.auth.login') }}"
                       class="inline-flex items-center gap-2 border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-medium py-2.5 px-5 rounded-lg transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                        </svg>
                        Admin
                    </a>
                </div>

                {{-- Mobile: Hamburger --}}
                <div class="md:hidden" x-data="{ open: false }">
                    <button @click="open = !open" type="button"
                            class="inline-flex items-center justify-center p-2 rounded-lg text-gray-600 hover:text-gray-900 hover:bg-gray-100 transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500"
                            aria-label="Toggle navigation menu">
                        <svg x-show="!open" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                        <svg x-cloak x-show="open" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>

                    {{-- Mobile Menu Dropdown --}}
                    <div x-cloak x-show="open"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 translate-y-0"
                         x-transition:leave-end="opacity-0 -translate-y-1"
                         @click.away="open = false"
                         class="absolute top-full left-0 right-0 bg-white border-b border-gray-200 shadow-lg z-50">
                        <div class="container mx-auto px-4 py-4 space-y-2">
                            <a href="{{ route('home') }}"
                               class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium {{ request()->routeIs('home') ? 'bg-primary-50 text-primary-700' : 'text-gray-700 hover:bg-gray-50' }} transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                                </svg>
                                Beranda
                            </a>
                            <hr class="border-gray-100">
                            <a href="{{ route('filament.client.auth.login') }}"
                               class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                </svg>
                                Masuk Klien
                            </a>
                            <a href="{{ route('filament.admin.auth.login') }}"
                               class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                                </svg>
                                Admin
                            </a>
                        </div>
                    </div>
                </div>

                {{-- Mobile: Logo text (shown on small screens where full text is hidden) --}}
                <div class="sm:hidden flex-1 min-w-0 ml-2">
                    <h1 class="text-base font-bold text-primary-900 truncate">PENDEKAR1721</h1>
                </div>
            </div>
        </div>
    </header>

    {{-- ============================================ --}}
    {{-- NAVIGATION BAR — Blue, horizontal             --}}
    {{-- ============================================ --}}
    <nav class="bg-primary-700 shadow-md relative z-40">
        <div class="container mx-auto px-4 sm:px-6">
            <div class="flex items-center h-12">
                <a href="{{ route('home') }}"
                   class="flex items-center gap-2 px-5 h-full text-sm font-semibold transition-colors duration-200
                          {{ request()->routeIs('home') ? 'bg-primary-900 text-white' : 'text-primary-100 hover:bg-primary-800 hover:text-white' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                    </svg>
                    Beranda
                </a>
            </div>
        </div>
    </nav>

    {{-- ============================================ --}}
    {{-- MAIN CONTENT                                  --}}
    {{-- ============================================ --}}
    <main class="flex-1">
        @yield('content')
    </main>

    {{-- ============================================ --}}
    {{-- FOOTER — Professional government style        --}}
    {{-- ============================================ --}}
    <footer class="bg-primary-900 text-white mt-auto">
        {{-- Main Footer --}}
        <div class="container mx-auto px-4 sm:px-6 py-12">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 lg:gap-12">
                {{-- Identity --}}
                <div class="lg:col-span-2">
                    <div class="flex items-start gap-4 mb-4">
                        <img src="{{ asset('images/dinsos-logo-01.png') }}" class="h-14 w-auto flex-shrink-0 brightness-0 invert opacity-90" alt="Logo Dinas Sosial Provinsi Jawa Barat" />
                        <div>
                            <h3 class="text-lg font-bold tracking-tight">PENDEKAR1721</h3>
                            <p class="text-primary-200 text-sm leading-relaxed">
                                UPTD Pusat Pelayanan Sosial Griya Bina Remaja<br>
                                Dinas Sosial Pemerintah Provinsi Jawa Barat
                            </p>
                        </div>
                    </div>
                    <p class="text-primary-300 text-sm leading-relaxed max-w-lg mt-4">
                        Direktori profil tenaga kerja terampil yang telah mendapatkan pelatihan keterampilan dari UPTD PPSGBR Dinas Sosial Pemerintah Provinsi Jawa Barat.
                    </p>
                </div>

                {{-- Quick Links --}}
                <div>
                    <h4 class="text-sm font-semibold text-primary-200 uppercase tracking-wider mb-4">Navigasi</h4>
                    <ul class="space-y-2.5">
                        <li>
                            <a href="{{ route('home') }}" class="text-sm text-primary-300 hover:text-white transition-colors duration-200 flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                                </svg>
                                Beranda
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('filament.client.auth.login') }}" class="text-sm text-primary-300 hover:text-white transition-colors duration-200 flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                </svg>
                                Portal Klien
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('filament.admin.auth.login') }}" class="text-sm text-primary-300 hover:text-white transition-colors duration-200 flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                                </svg>
                                Portal Admin
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        {{-- Copyright Bar --}}
        <div class="border-t border-primary-800">
            <div class="container mx-auto px-4 sm:px-6 py-4">
                <p class="text-center text-sm text-primary-400">
                    &copy; {{ date('Y') }} PENDEKAR1721 — UPTD Pusat Pelayanan Sosial Griya Bina Remaja, Dinas Sosial Pemerintah Provinsi Jawa Barat
                </p>
            </div>
        </div>
    </footer>
@endsection
