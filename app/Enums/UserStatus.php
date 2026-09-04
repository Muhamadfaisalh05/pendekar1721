<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserStatus: string implements HasLabel
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Draft => 'Tidak Aktif (Draft)',
            self::Published => 'Aktif',
            self::Archived => 'Diarsipkan',
        };
    }
}
