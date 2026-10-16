<?php

namespace App\Enums;

enum WorkLocation: string
{
    case Onshore = 'onshore';
    case Offshore = 'offshore';

    public function label(): string
    {
        return match ($this) {
            self::Onshore => 'Onshore',
            self::Offshore => 'Offshore',
        };
    }
}
