<x-filament-panels::page>
    @php
        $stats         = $this->getStatistik();
        $user          = auth()->user();
        $pelatihanSaya = $this->getPelatihanSaya();
        $profil        = $stats['kelengkapan_profil'];
        $kosong        = $stats['kolom_profil_kosong'];
        $statusAkun    = $this->getStatusAkun();
        $inisial       = collect(explode(' ', trim($user->name)))
            ->filter()
            ->take(2)
            ->map(fn ($kata) => strtoupper(mb_substr($kata, 0, 1)))
            ->implode('');

        // Warna indikator kelengkapan profil (hijau >= 80, kuning >= 50, merah < 50).
        $warnaProfil = $profil >= 80 ? '#34d399' : ($profil >= 50 ? '#fbbf24' : '#fb7185');
    @endphp

    {{-- ============ HERO: salam & status akun (hanya data milik sendiri) ============ --}}
    <div class="rounded-xl mb-4 flex flex-col md:flex-row items-center gap-4 p-4"
         style="background: linear-gradient(135deg, #064e3b 0%, #065f46 45%, #047857 100%);">

        <div class="flex items-center justify-center rounded-full flex-none text-2xl font-bold"
             style="width:3.5rem; height:3.5rem; background:rgba(255,255,255,.92); color:#064e3b;">
            {{ $inisial }}
        </div>

        <div class="flex-1 min-w-0">
            <p class="text-xs font-semibold" style="color:#a7f3d0; letter-spacing:.08em;">Selamat Datang</p>
            <p class="text-2xl font-bold truncate" style="color:#ffffff;">{{ $user->name }}</p>
            <p class="text-xs mt-1" style="color:#a7f3d0;">
                Status akun:
                <span class="font-semibold" style="color:#ecfdf5;">{{ $statusAkun }}</span>
                &middot; Panel Klien Pendekar 1721
            </p>
        </div>

        <div class="w-full" style="max-width:18rem;">
            <div class="rounded-lg p-3" style="background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.16);">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium" style="color:#d1fae5;">Kelengkapan Profil</span>
                    <span class="text-sm font-bold" style="color:{{ $warnaProfil }};">{{ $profil }}%</span>
                </div>
                <div class="w-full rounded-full overflow-hidden mt-1" style="height:.5rem; background:rgba(255,255,255,.22);">
                    <div class="rounded-full" style="height:.5rem; width:{{ $profil }}%; background:{{ $warnaProfil }};"></div>
                </div>
                <p class="text-xs mt-1" style="color:#a7f3d0;">
                    @if ($profil >= 100)
                        Profil Anda sudah lengkap.
                    @else
                        {{ count($kosong) }} kolom lagi untuk profil lengkap.
                    @endif
                </p>
            </div>
        </div>
    </div>

    {{-- ========================= KARTU RINGKASAN ========================= --}}
    <div class="grid gap-4 mb-4 md:grid-cols-3">

        {{-- 1. Jumlah Alumni: hanya ANGKA agregat, bukan data orang lain --}}
        <div class="rounded-xl p-4 border shadow-sm" style="background:#ffffff; border-color:#e5e7eb;">
            <div class="flex items-center gap-3">
                <span class="flex items-center justify-center rounded-lg flex-none"
                      style="width:2.5rem; height:2.5rem; background:#ecfdf5; color:#047857;">
                    <x-filament::icon icon="heroicon-o-users" class="h-5 w-5" />
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-semibold" style="color:#6b7280;">Jumlah Alumni</p>
                    <p class="text-2xl font-bold" style="color:#111827;">{{ $stats['total_alumni'] }}</p>
                </div>
            </div>
            <div class="mt-1">
                @if ($stats['total_alumni_belum_final'])
                    <span class="inline-flex items-center gap-1 rounded-full text-xs font-semibold"
                          style="background:#fffbeb; color:#b45309; padding:.15rem .5rem;">
                        <x-filament::icon icon="heroicon-m-clock" class="h-4 w-4" />
                        Angka sementara (belum final)
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 rounded-full text-xs font-semibold"
                          style="background:#ecfdf5; color:#047857; padding:.15rem .5rem;">
                        <x-filament::icon icon="heroicon-m-check-circle" class="h-4 w-4" />
                        Data final
                    </span>
                @endif
            </div>
        </div>

        {{-- 2. Pelatihan Saya --}}
        <div class="rounded-xl p-4 border shadow-sm" style="background:#ffffff; border-color:#e5e7eb;">
            <div class="flex items-center gap-3">
                <span class="flex items-center justify-center rounded-lg flex-none"
                      style="width:2.5rem; height:2.5rem; background:#eff6ff; color:#1d4ed8;">
                    <x-filament::icon icon="heroicon-o-academic-cap" class="h-5 w-5" />
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-semibold" style="color:#6b7280;">Pelatihan Saya</p>
                    <p class="text-2xl font-bold" style="color:#111827;">{{ $stats['pelatihan_saya'] }}</p>
                </div>
            </div>
            <p class="text-xs mt-1" style="color:#6b7280;">Program pelatihan yang Anda ikuti</p>
        </div>

        {{-- 3. Kelengkapan Profil --}}
        <div class="rounded-xl p-4 border shadow-sm" style="background:#ffffff; border-color:#e5e7eb;">
            <div class="flex items-center gap-3">
                <span class="flex items-center justify-center rounded-lg flex-none"
                      style="width:2.5rem; height:2.5rem; background:#f5f3ff; color:#6d28d9;">
                    <x-filament::icon icon="heroicon-o-identification" class="h-5 w-5" />
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-semibold" style="color:#6b7280;">Kelengkapan Profil</p>
                    <p class="text-2xl font-bold" style="color:#111827;">{{ $profil }}%</p>
                </div>
            </div>
            <div class="w-full rounded-full overflow-hidden mt-1" style="height:.5rem; background:#e5e7eb;">
                <div class="rounded-full" style="height:.5rem; width:{{ $profil }}%; background:{{ $warnaProfil }};"></div>
            </div>
        </div>
    </div>

    {{-- =================== PELATIHAN SAYA + LENGKAPI PROFIL =================== --}}
    <div class="grid gap-4 mb-4 md:grid-cols-2">

        {{-- Daftar pelatihan milik sendiri (read-only) --}}
        <div class="rounded-xl border shadow-sm overflow-hidden" style="background:#ffffff; border-color:#e5e7eb;">
            <div class="flex items-center justify-between p-4 border-b" style="border-color:#f3f4f6;">
                <div class="flex items-center gap-2">
                    <span class="flex items-center justify-center rounded-lg"
                          style="width:2rem; height:2rem; background:#eff6ff; color:#1d4ed8;">
                        <x-filament::icon icon="heroicon-o-academic-cap" class="h-4 w-4" />
                    </span>
                    <p class="text-sm font-semibold" style="color:#111827;">Pelatihan Saya</p>
                </div>
                <span class="rounded-full text-xs" style="background:#f3f4f6; color:#4b5563; padding:.15rem .5rem;">
                    {{ $pelatihanSaya->count() }} program
                </span>
            </div>

            <div class="p-4">
                @if ($pelatihanSaya->isNotEmpty())
                    <ul>
                        @foreach ($pelatihanSaya as $pelatihan)
                            <li class="flex items-center gap-2 rounded-lg p-3" style="background:#f9fafb; margin-bottom:.5rem;">
                                <x-filament::icon icon="heroicon-m-check-circle" class="h-4 w-4 flex-none" style="color:#059669;" />
                                <span class="text-sm truncate" style="color:#374151;">{{ $pelatihan->title }}</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="flex flex-col items-center justify-center text-center" style="padding:1.75rem 0;">
                        <span class="flex items-center justify-center rounded-full"
                              style="width:3rem; height:3rem; background:#f3f4f6; color:#9ca3af;">
                            <x-filament::icon icon="heroicon-o-inbox" class="h-5 w-5" />
                        </span>
                        <p class="text-sm font-semibold mt-1" style="color:#4b5563;">Belum ada data pelatihan</p>
                        <p class="text-xs" style="color:#9ca3af;">Data pelatihan diisi oleh admin Pendekar 1721.</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Lengkapi profil: daftar kolom kosong + tombol --}}
        <div class="rounded-xl border shadow-sm overflow-hidden" style="background:#ffffff; border-color:#e5e7eb;">
            <div class="flex items-center gap-2 p-4 border-b" style="border-color:#f3f4f6;">
                <span class="flex items-center justify-center rounded-lg"
                      style="width:2rem; height:2rem; background:#fffbeb; color:#b45309;">
                    <x-filament::icon icon="heroicon-o-identification" class="h-4 w-4" />
                </span>
                <p class="text-sm font-semibold" style="color:#111827;">Lengkapi Profil</p>
            </div>

            <div class="p-4">
                <p class="text-sm" style="color:#374151;">
                    Profil Anda terisi <span class="font-bold" style="color:#047857;">{{ $profil }}%</span>.
                    Profil lengkap memudahkan penyaluran kerja dan pelatihan yang sesuai.
                </p>

                @if ($kosong !== [])
                    <p class="text-xs font-semibold mt-1" style="color:#6b7280;">Yang masih kosong:</p>
                    <ul class="mt-1">
                        @foreach ($kosong as $label)
                            <li class="flex items-center gap-2 text-xs" style="color:#b45309; margin-bottom:.25rem;">
                                <x-filament::icon icon="heroicon-m-exclamation-circle" class="h-4 w-4 flex-none" />
                                {{ $label }}
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-xs mt-1" style="color:#047857;">Semua kolom wajib sudah terisi. Terima kasih!</p>
                @endif

                <a href="{{ \App\Filament\Client\Pages\UserProfileEditPage::getUrl() }}"
                   class="inline-flex items-center gap-2 rounded-lg text-xs font-semibold mt-1"
                   style="background:#047857; color:#ffffff; padding:.5rem .9rem;">
                    <x-filament::icon icon="heroicon-m-pencil-square" class="h-4 w-4" />
                    Lengkapi Profil
                </a>
            </div>
        </div>
    </div>


    {{-- =========================== AKSES CEPAT =========================== --}}
    <div class="rounded-xl border shadow-sm p-4" style="background:#ffffff; border-color:#e5e7eb;">
        <div class="flex items-center gap-2 mb-4">
            <span class="flex items-center justify-center rounded-lg"
                  style="width:2rem; height:2rem; background:#ecfdf5; color:#047857;">
                <x-filament::icon icon="heroicon-o-bolt" class="h-4 w-4" />
            </span>
            <p class="text-sm font-semibold" style="color:#111827;">Akses Cepat</p>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            @foreach ($this->getTautanCepat() as $tautan)
                <a href="{{ $tautan['url'] }}" class="flex items-start gap-3 rounded-xl border p-4"
                   style="border-color:#e5e7eb; background:#ffffff;">
                    <span class="flex items-center justify-center rounded-lg flex-none"
                          style="width:2.5rem; height:2.5rem; background:#f0fdf4; color:#047857;">
                        <x-filament::icon :icon="$tautan['ikon']" class="h-5 w-5" />
                    </span>
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold" style="color:#111827;">{{ $tautan['label'] }}</span>
                        <span class="block text-xs mt-1" style="color:#6b7280;">{{ $tautan['keterangan'] }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>

