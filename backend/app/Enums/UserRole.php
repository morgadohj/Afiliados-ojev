<?php

namespace App\Enums;

enum UserRole: string
{
    case Administrator = 'administrador';
    case AffiliateRegistrar = 'afiliador';

    public function label(): string
    {
        return match ($this) {
            self::Administrator => 'Administrador',
            self::AffiliateRegistrar => 'Afiliador',
        };
    }
}
