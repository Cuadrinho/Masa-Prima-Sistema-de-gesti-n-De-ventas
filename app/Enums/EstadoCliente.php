<?php

namespace App\Enums;

enum EstadoCliente: string
{
    case Pendiente = 'pendiente';
    case Activo = 'activo';
    case Rechazado = 'rechazado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Alta pendiente',
            self::Activo => 'Activo',
            self::Rechazado => 'Rechazado',
        };
    }

    public function clases(): string
    {
        return match ($this) {
            self::Pendiente => 'bg-queso text-carbon',
            self::Activo => 'bg-albahaca-claro text-albahaca',
            self::Rechazado => 'border border-linea bg-white text-tinta line-through',
        };
    }
}
