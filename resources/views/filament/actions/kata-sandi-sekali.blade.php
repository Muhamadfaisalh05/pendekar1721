{{-- Modal kata sandi sementara: hanya ditampilkan SEKALI (FASE 3). --}}
<div class="space-y-4">
    @if (filled($nama))
        <p class="text-sm text-gray-600 dark:text-gray-300">
            Akun: <span class="font-semibold">{{ $nama }}</span>
        </p>
    @endif

    <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 dark:border-white/10 dark:bg-white/5">
        <code class="block break-all font-mono text-base">{{ $password }}</code>
    </div>

    <p class="text-sm text-danger-600 dark:text-danger-400">
        Kata sandi ini tidak disimpan dalam bentuk terbaca. Bila hilang, gunakan aksi
        &quot;Reset Kata Sandi&quot; untuk membuat yang baru.
    </p>
</div>
