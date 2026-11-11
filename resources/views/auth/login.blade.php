<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#083668">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Masuk — PENDEKAR1721</title>

    @vite(['resources/sass/app.scss'])
</head>

<body class="min-h-screen bg-slate-100 text-slate-800 antialiased">
    <main class="mx-auto flex min-h-screen w-full max-w-6xl items-center justify-center p-4">
        <div class="grid w-full overflow-hidden rounded-3xl bg-white shadow-xl lg:grid-cols-[1.1fr_1fr]">

            {{-- Panel kiri: identitas aplikasi + peta role --}}
            <section class="hidden flex-col justify-between gap-8 p-10 text-white lg:flex"
                style="background: linear-gradient(150deg, #083668 0%, #0c4a82 55%, #0a6b7a 100%);">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.25em] text-blue-200">Dinas Sosial Jawa Barat</p>
                    <h1 class="mt-3 text-3xl font-black leading-tight">PENDEKAR1721</h1>
                    <p class="mt-2 text-sm text-blue-100">
                        Direktori Tenaga Kerja Terampil &amp; Sistem Monitoring Rapor Karakter Anak
                        (SEMARAK) — UPTD Pusat Pelayanan Sosial Griya Bina Remaja.
                    </p>
                </div>

                <div class="space-y-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-blue-200">Satu login, tiga dashboard</p>

                    <div class="flex items-start gap-3 rounded-2xl bg-white/10 p-3">
                        <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white/20 text-sm font-bold">A</span>
                        <div>
                            <p class="text-sm font-semibold">Administrator</p>
                            <p class="text-xs text-blue-100">Menu penuh pendekar1721 &amp; modul absensi → <span class="font-mono">/admin</span></p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3 rounded-2xl bg-white/10 p-3">
                        <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white/20 text-sm font-bold">S</span>
                        <div>
                            <p class="text-sm font-semibold">Admin Absensi</p>
                            <p class="text-xs text-blue-100">Hanya modul SEMARAK → <span class="font-mono">/absensi</span></p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3 rounded-2xl bg-white/10 p-3">
                        <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white/20 text-sm font-bold">K</span>
                        <div>
                            <p class="text-sm font-semibold">Klien</p>
                            <p class="text-xs text-blue-100">Data peserta &amp; profil → <span class="font-mono">/klien</span></p>
                        </div>
                    </div>
                </div>

                <p class="text-xs text-blue-200">
                    &copy; {{ date('Y') }} UPTD PPSGBR · Dinas Sosial Provinsi Jawa Barat
                </p>
            </section>

            {{-- Panel kanan: form login --}}
            <section class="p-8 sm:p-10">
                <div class="lg:hidden">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">PENDEKAR1721</p>
                    <h1 class="mt-1 text-2xl font-black" style="color:#083668">Masuk</h1>
                    <p class="mt-1 text-sm text-slate-500">Satu login untuk admin, admin absensi, dan klien.</p>
                </div>

                <div class="hidden lg:block">
                    <h2 class="text-2xl font-black" style="color:#083668">Masuk</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Gunakan email dan password yang diberikan administrator. Dashboard tujuan
                        ditentukan otomatis dari role akun Anda.
                    </p>
                </div>

                @if (session('status'))
                    <div class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
                        role="status">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mt-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"
                        role="alert">
                        <ul class="list-disc space-y-1 pl-4">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-4" novalidate>
                    @csrf

                    <div>
                        <label for="email" class="block text-sm font-semibold text-slate-700">Email</label>
                        <input id="email" name="email" type="email" required autofocus autocomplete="username"
                            value="{{ old('email') }}" placeholder="nama@instansi.go.id"
                            class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm shadow-sm focus:border-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-700/20">
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-semibold text-slate-700">Password</label>
                        <input id="password" name="password" type="password" required autocomplete="current-password"
                            placeholder="Masukkan password"
                            class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm shadow-sm focus:border-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-700/20">
                    </div>

                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" name="remember" value="1" @checked(old('remember'))
                            class="h-4 w-4 rounded border-slate-300 focus:ring-2 focus:ring-primary-700/30">
                        Ingat saya di perangkat ini
                    </label>

                    <button type="submit"
                        class="w-full rounded-xl px-4 py-3 text-sm font-bold text-white shadow-lg transition hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-primary-700/40"
                        style="background:linear-gradient(135deg,#083668 0%,#0c4a82 100%)">
                        Masuk
                    </button>
                </form>

                @if (app()->environment('local'))
                    <div class="mt-8 rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Akun demo (hanya tampil di env <span class="font-mono">local</span>)
                        </p>
                        <ul class="mt-2 space-y-1 font-mono text-xs text-slate-600">
                            <li>admin@pendekar1721.test / Admin#1721 &rarr; /admin</li>
                            <li>absensi@pendekar1721.test / Absensi#1721 &rarr; /absensi</li>
                            <li>klien@pendekar1721.test / Klien#1721 &rarr; /klien</li>
                        </ul>
                    </div>
                @endif

                <p class="mt-6 text-center text-xs text-slate-500">
                    <a href="{{ route('home') }}" class="font-semibold hover:underline" style="color:#083668">Kembali
                        ke beranda</a>
                    <span class="mx-2 text-slate-300">|</span>
                    Lupa password? Hubungi administrator.
                </p>
            </section>
        </div>
    </main>
</body>

</html>
