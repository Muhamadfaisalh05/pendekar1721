<?php

namespace App\Filament\Widgets;

use App\Enums\UserStatus;
use App\Filament\Resources\AnakResource;
use App\Filament\Resources\UserResource;
use App\Services\DasborAdminService;
use App\Support\SchemaGuard;
use Filament\Widgets\Widget;

/**
 * Panel "Perlu Tindakan": hanya JUMLAH dan tautan ke daftar yang sudah
 * difilter (tanpa nama/email). Angka diambil dari cache dashboard yang sama
 * dengan kartu ringkasan supaya tidak menambah query.
 */
class PerluTindakanWidget extends Widget
{
    protected static string $view = 'filament.widgets.perlu-tindakan';

    // Dirender langsung (bukan lazy): angka tampil pada paint pertama dan bisa
    // diuji lewat GET /admin tanpa permintaan Livewire tambahan.
    protected static bool $isLazy = false;

    protected static ?int $sort = 5;

    // Tiga panel di dalamnya memakai `md:grid-cols-3`, jadi butuh lebar penuh.
    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $data = app(DasborAdminService::class)->ringkasan();
        $perlu = $data['perlu_tindakan'];

        return [
            'draft' => $perlu['draft'],
            'tautanDraft' => UserResource::getUrl('index', [
                'tableFilters' => ['status' => ['value' => UserStatus::Draft->value]],
            ]),
            'migrasiTertunda' => $perlu['migrasi_tertunda'],
            'anakTanpaAkun' => $perlu['anak_tanpa_akun'],
            // Tautan langsung ke daftar anak yang SUDAH difilter "belum punya
            // akun klien" — filter itu hanya dipasang bila kolom tautan ada.
            'tautanAnak' => SchemaGuard::usersPunyaTautanAnak()
                ? AnakResource::getUrl('index', ['tableFilters' => ['tanpa_akun' => ['isActive' => 1]]])
                : AnakResource::getUrl('index'),
        ];
    }
}
