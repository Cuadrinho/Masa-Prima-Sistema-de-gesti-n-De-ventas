<?php

namespace App\Enums;

enum Franja: string
{
    case Manana = 'manana';
    case Tarde = 'tarde';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Manana => 'Mañana',
            self::Tarde => 'Tarde',
        };
    }

    public function horario(): string
    {
        return match ($this) {
            self::Manana => '8 a 12 h',
            self::Tarde => '14 a 18 h',
        };
    }
}
