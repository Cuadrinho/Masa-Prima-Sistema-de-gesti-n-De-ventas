<?php

namespace App\Enums;

enum Rol: string
{
    case Admin = 'admin';
    case Cocina = 'cocina';
    case Cliente = 'cliente';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Cocina => 'Cocina',
            self::Cliente => 'Cliente',
        };
    }

    public function esPersonal(): bool
    {
        return $this !== self::Cliente;
    }
}
