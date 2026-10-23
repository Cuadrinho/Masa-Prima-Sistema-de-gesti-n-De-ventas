<?php

namespace App\Support;

use Carbon\CarbonInterface;

class Formato
{
    /**
     * "$ 12.500" o el placeholder del diseño si todavía no hay precio cargado.
     */
    public static function dinero(float|string|null $monto, string $placeholder = 'TOTAL'): string
    {
        if ($monto === null) {
            return '$ ['.$placeholder.']';
        }

        $monto = (float) $monto;

        return '$ '.number_format($monto, fmod($monto, 1.0) == 0.0 ? 0 : 2, ',', '.');
    }

    /**
     * Fecha con formato en español sin los puntos de las abreviaturas ("Mié 7 oct").
     */
    public static function fecha(CarbonInterface $fecha, string $formato = 'D j M'): string
    {
        return ucfirst(str_replace('.', '', $fecha->locale('es')->translatedFormat($formato)));
    }
}
