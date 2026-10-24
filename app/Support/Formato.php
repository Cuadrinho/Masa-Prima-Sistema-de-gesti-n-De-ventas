<?php

namespace App\Support;

use Carbon\CarbonInterface;

class Formato
{
    /**
     * "$ 12.500", o una raya si todavía no hay monto.
     */
    public static function dinero(float|string|null $monto): string
    {
        if ($monto === null) {
            return '—';
        }

        $monto = (float) $monto;

        return '$ '.number_format($monto, fmod($monto, 1.0) == 0.0 ? 0 : 2, ',', '.');
    }

    /**
     * "12.500" para mostrar dentro de un campo, sin el signo.
     */
    public static function monto(float|string|null $monto): string
    {
        return $monto === null ? '' : substr(self::dinero($monto), 2);
    }

    /**
     * Lee un monto tipeado a mano ("12.500", "12500,50", "$ 12.500") o null si no es un número.
     */
    public static function leerMonto(?string $texto): ?float
    {
        $texto = preg_replace('/[^\d.,]/', '', (string) $texto);

        if (str_contains($texto, ',')) {
            $texto = str_replace(['.', ','], ['', '.'], $texto);
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $texto)) {
            $texto = str_replace('.', '', $texto);
        }

        return is_numeric($texto) ? (float) $texto : null;
    }

    /**
     * "+8 %" o "−5 %" entre dos montos, o null si no se puede calcular.
     */
    public static function variacion(float|string|null $anterior, float|string|null $nuevo): ?string
    {
        if ($anterior === null || $nuevo === null || (float) $anterior == 0.0) {
            return null;
        }

        $porcentaje = round(((float) $nuevo / (float) $anterior - 1) * 100, 1);

        if ($porcentaje == 0.0) {
            return 'sin cambios';
        }

        return ($porcentaje > 0 ? '+' : '−').str_replace('.', ',', (string) abs($porcentaje)).' %';
    }

    /**
     * Fecha con formato en español sin los puntos de las abreviaturas ("Mié 7 oct").
     */
    public static function fecha(CarbonInterface $fecha, string $formato = 'D j M'): string
    {
        return ucfirst(str_replace('.', '', $fecha->locale('es')->translatedFormat($formato)));
    }
}
