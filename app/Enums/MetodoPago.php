<?php

namespace App\Enums;

enum MetodoPago: string
{
    case Transferencia = 'transferencia';
    case MercadoPago = 'mercadopago';
    case CuentaCorriente = 'cuenta_corriente';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Transferencia => 'Transferencia',
            self::MercadoPago => 'Mercado Pago',
            self::CuentaCorriente => 'Cuenta corriente',
        };
    }
}
