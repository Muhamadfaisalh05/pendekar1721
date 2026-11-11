<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#083668">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Masuk — PENDEKAR1721</title>

    <link rel="shortcut icon" href="{{ url(asset('favicon.ico')) }}">
    <link rel="preconnect" href="https://rsms.me">
    <link rel="stylesheet" href="https://rsms.me/inter/inter.css">

    @vite(['resources/sass/app.scss'])
</head>

<body class="relative min-h-screen overflow-x-hidden bg-primary-900 text-slate-800 antialiased">
    {{-- Latar foto hero + overlay biru, senada dengan landing page --}}
    <div class="pointer-events-none fixed inset-0 -z-10" aria-hidden="true">
        <img src="{{ asset('images/hero-ppsgbr.png') }}" alt="" class="h-full w-full object-cover"
            style="filter: brightness(0.58) saturate(1.05)">
        <div class="absolute inset-0"
            style="background: linear-gradient(105deg, rgba(2,16,34,0.93) 0%, rgba(8,54,104,0.88) 45%, rgba(8,54,104,0.45) 100%)">
        </div>
    </div>

    <div class="flex min-h-screen flex-col">

        <header class="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-4 py-6 sm:px-8">
            <a href="{{ route('home') }}" class="flex items-center gap-3">
                <img src="{{ asset('images/dinsos-logo-01.png') }}" alt="Logo Dinas Sosial Provinsi Jawa Barat"
                    class="h-11 w-auto rounded-xl bg-white/90 p-1">
                <span class="leading-tight text-white">
                    <span class="block text-lg font-black tracking-tight">PENDEKAR1721</span>
                    <span class="block text-[10px] font-semibold uppercase tracking-[0.18em] text-blue-200">
                        UPTD PPSGBR · Dinas Sosial Jabar
                    </span>
                </span>
            </a>

            <a href="{{ route('home') }}"
                class="rounded-full border border-white/25 px-4 py-2 text-xs font-semibold text-white/90 transition hover:bg-white/10">
                &larr; Beranda
            </a>
        </header>

        <main
            class="mx-auto grid w-full max-w-6xl flex-1 items-center gap-10 px-4 pb-12 sm:px-8 lg:grid-cols-[1.05fr_0.95fr] lg:gap-16">

            {{-- Kiri: identitas PENDEKAR1721 --}}
            <section class="hidden text-white lg:block">
                <span class="text-[11px] font-bold uppercase tracking-[0.24em] text-blue-200">
                    UPTD PPSGBR
                </span>

                <h1 class="mt-4 text-4xl font-black leading-[1.05] tracking-tight xl:text-5xl">
                    PENDEKAR1721
                </h1>

                <p class="mt-3 text-base font-semibold text-blue-100">
                    UPTD Pusat Pelayanan Sosial Griya Bina Remaja
                </p>

                <p class="mt-5 max-w-xl text-sm leading-relaxed text-blue-100/90">
                    Direktori profil tenaga kerja terampil yang telah mendapatkan pelatihan keterampilan dari
                    UPTD PPSGBR Dinas Sosial Pemerintah Provinsi Jawa Barat, dengan fokus pada peningkatan
                    kualitas sumber daya manusia, penempatan kerja, dan penguatan kapasitas komunitas.
                </p>
            </section>

            {{-- Kanan: kartu formulir --}}
            <section class="w-full rounded-3xl border border-white/25 bg-white/95 p-6 shadow-2xl backdrop-blur sm:p-8">

                {{-- Identitas ringkas (tanpa judul "Masuk ke akun Anda") --}}
                <div class="mb-6 flex items-center gap-3">
                    <img src="{{ asset('images/dinsos-logo-01.png') }}" alt="Logo PENDEKAR1721"
                        class="h-10 w-auto rounded-lg">
                    <div class="leading-tight">
                        <div class="text-base font-black tracking-tight text-primary-900">PENDEKAR1721</div>
                        <div class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">
                            UPTD PPSGBR
                        </div>
                    </div>
                </div>

                @if (session('status'))
                    <div class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800"
                        role="status">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mt-5 flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3"
                        role="alert">
                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-rose-500" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 9v3.75m0 3.75h.008M10.34 3.94l-7.4 12.8A1.7 1.7 0 0 0 4.4 19.5h15.2a1.7 1.7 0 0 0 1.47-2.76l-7.4-12.8a1.7 1.7 0 0 0-2.93 0Z" />
                        </svg>
                        <div class="text-sm text-rose-800">
                            @foreach ($errors->all() as $message)
                                <p class="leading-relaxed">{{ $message }}</p>
                            @endforeach
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-5" novalidate>
                    @csrf

                    <div>
                        <label for="email" class="mb-1.5 block text-sm font-semibold text-slate-700">
                            Alamat email
                        </label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="1.6" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                                </svg>
                            </span>
                            <input id="email" name="email" type="email" value="{{ old('email') }}" required
                                autocomplete="username" autofocus placeholder="nama@pendekar1721.test"
                                class="w-full rounded-xl border border-slate-300 bg-white py-3 pl-11 pr-4 text-sm text-slate-800 shadow-sm transition placeholder:text-slate-400 focus:border-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-500/30 @error('email') border-rose-400 @enderror">
                        </div>
                    </div>

                    <div>
                        <label for="password" class="mb-1.5 block text-sm font-semibold text-slate-700">
                            Kata sandi
                        </label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="1.6" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                </svg>
                            </span>
                            <input id="password" name="password" type="password" required autocomplete="current-password"
                                placeholder="Masukkan kata sandi"
                                class="w-full rounded-xl border border-slate-300 bg-white py-3 pl-11 pr-12 text-sm text-slate-800 shadow-sm transition placeholder:text-slate-400 focus:border-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-500/30 @error('password') border-rose-400 @enderror">
                            <button type="button" data-password-toggle="password"
                                class="absolute inset-y-0 right-0 flex items-center px-3.5 text-slate-400 transition hover:text-slate-600"
                                aria-label="Tampilkan atau sembunyikan kata sandi">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="1.6" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.964-7.178Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-3">
                        <label for="remember" class="flex items-center gap-2.5 text-sm text-slate-600">
                            <input id="remember" name="remember" type="checkbox" value="1" @checked(old('remember'))
                                class="h-4 w-4 rounded border-slate-300 text-primary-700 focus:ring-primary-500">
                            Ingat saya
                        </label>
                        <a href="{{ route('home') }}#informasi"
                            class="text-xs font-semibold text-primary-700 hover:underline">
                            Butuh bantuan?
                        </a>
                    </div>

                    <button type="submit"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-primary-800 to-primary-600 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-primary-900/20 transition hover:from-primary-900 hover:to-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                        Masuk
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                            aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                        </svg>
                    </button>
                </form>

                <p class="mt-6 border-t border-slate-200 pt-5 text-xs leading-relaxed text-slate-500">
                    Belum memiliki akses? Silakan hubungi administrator UPTD PPSGBR untuk pendaftaran akun.
                </p>
            </section>
        </main>

        <footer class="px-4 pb-6 text-center text-[11px] text-blue-100/70">
            &copy; {{ date('Y') }} PENDEKAR1721 — UPTD Pusat Pelayanan Sosial Griya Bina Remaja
        </footer>
    </div>

    <script>
        document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
            button.addEventListener('click', function () {
                var input = document.getElementById(button.getAttribute('data-password-toggle'));

                if (! input) {
                    return;
                }

                input.type = input.type === 'password' ? 'text' : 'password';
            });
        });
    </script>
</body>

</html>
