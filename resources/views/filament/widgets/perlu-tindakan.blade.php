{{--
    Panel "Perlu Tindakan" dashboard Admin (panel oranye/pemerintahan).
    PRIVASI: hanya angka agregat + tautan; tidak ada nama/email pengguna.
    Catatan CSS: warna memakai inline style karena panel Admin tidak memakai
    tema Tailwind kustom (kelas warna arbitrer tidak ikut ter-compile).
--}}
<x-filament-widgets::widget>
    <x-filament::section
        heading="Perlu Tindakan"
        description="Pekerjaan yang menunggu operator Pendekar 1721"
        icon="heroicon-o-exclamation-triangle"
        icon-color="warning"
    >
        <div class="grid gap-4 md:grid-cols-3">

            {{-- 1. Akun Draft menunggu kelulusan --}}
            <a href="{{ $tautanDraft }}" class="flex flex-col justify-between rounded-xl p-4"
               style="border:1px solid #fcd34d; background:#fffbeb;">
                <div class="flex items-start gap-3">
                    <span class="flex items-center justify-center rounded-lg flex-none"
                          style="width:2.25rem; height:2.25rem; background:#f59e0b; color:#ffffff;">
                        <x-filament::icon icon="heroicon-o-clock" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold" style="color:#b45309;">Akun Draft</p>
                        <p class="text-2xl font-bold" style="color:#78350f;">{{ $draft }}</p>
                        <p class="text-xs" style="color:#b45309;">menunggu kelulusan / aktivasi</p>
                    </div>
                </div>
                <span class="inline-flex items-center gap-1 text-xs font-semibold mt-1" style="color:#92400e;">
                    Lihat daftar akun Draft
                    <x-filament::icon icon="heroicon-m-arrow-right" class="h-4 w-4" />
                </span>
            </a>

            {{-- 2. Status migrasi skema Fase 2 --}}
            <div class="flex flex-col justify-between rounded-xl p-4"
                 style="border:1px solid {{ $migrasiTertunda ? '#fda4af' : '#6ee7b7' }}; background:{{ $migrasiTertunda ? '#fff1f2' : '#ecfdf5' }};">
                <div class="flex items-start gap-3">
                    <span class="flex items-center justify-center rounded-lg flex-none"
                          style="width:2.25rem; height:2.25rem; background:{{ $migrasiTertunda ? '#e11d48' : '#059669' }}; color:#ffffff;">
                        <x-filament::icon
                            :icon="$migrasiTertunda ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle'"
                            class="h-5 w-5"
                        />
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold" style="color:{{ $migrasiTertunda ? '#be123c' : '#047857' }};">
                            Migrasi Fase 2
                        </p>

                        @if ($migrasiTertunda)
                            <p class="text-sm font-bold" style="color:#881337;">Belum dijalankan</p>
                            <p class="text-xs" style="color:#be123c;">
                                Kolom tautan akun &amp; tanggal lulus belum ada.
                            </p>
                        @else
                            <p class="text-sm font-bold" style="color:#064e3b;">Skema lengkap</p>
                            <p class="text-xs" style="color:#047857;">
                                Tautan akun &amp; tanggal lulus tersedia.
                            </p>
                        @endif
                    </div>
                </div>

                @if ($migrasiTertunda)
                    <p class="text-xs mt-1" style="color:#9f1239;">
                        Jalankan <code style="background:#ffffff; padding:0 .2rem; border-radius:.25rem;">php artisan migrate</code>,
                        lalu muat ulang halaman.
                    </p>
                @else
                    <p class="text-xs mt-1" style="color:#047857;">Fitur kelulusan &amp; statistik alumni aktif.</p>
                @endif
            </div>

            {{-- 3. Anak binaan aktif tanpa akun Klien --}}
            @if ($anakTanpaAkun === null)
                <div class="flex flex-col justify-between rounded-xl p-4"
                     style="border:1px solid #e5e7eb; background:#f9fafb;">
                    <div class="flex items-start gap-3">
                        <span class="flex items-center justify-center rounded-lg flex-none"
                              style="width:2.25rem; height:2.25rem; background:#9ca3af; color:#ffffff;">
                            <x-filament::icon icon="heroicon-o-user-group" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-xs font-semibold" style="color:#4b5563;">Anak Tanpa Akun Klien</p>
                            <p class="text-sm font-bold" style="color:#374151;">Data belum tersedia</p>
                            <p class="text-xs" style="color:#6b7280;">
                                Periksa koneksi SEMARAK &amp; migrasi kolom tautan akun.
                            </p>
                        </div>
                    </div>
                </div>
            @else
                <a href="{{ $tautanAnak }}" class="flex flex-col justify-between rounded-xl p-4"
                   style="border:1px solid #7dd3fc; background:#f0f9ff;">
                    <div class="flex items-start gap-3">
                        <span class="flex items-center justify-center rounded-lg flex-none"
                              style="width:2.25rem; height:2.25rem; background:#0284c7; color:#ffffff;">
                            <x-filament::icon icon="heroicon-o-user-group" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-xs font-semibold" style="color:#0369a1;">Anak Tanpa Akun Klien</p>
                            <p class="text-2xl font-bold" style="color:#0c4a6e;">{{ $anakTanpaAkun }}</p>
                            <p class="text-xs" style="color:#0369a1;">binaan aktif belum tertaut akun</p>
                        </div>
                    </div>
                    <span class="inline-flex items-center gap-1 text-xs font-semibold mt-1" style="color:#075985;">
                        Lihat daftar anak binaan
                        <x-filament::icon icon="heroicon-m-arrow-right" class="h-4 w-4" />
                    </span>
                </a>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
