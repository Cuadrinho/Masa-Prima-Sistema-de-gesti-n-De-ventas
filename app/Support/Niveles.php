<?php

namespace App\Support;

/**
 * Niveles de precio definidos en config('tienda.niveles'), por clave
 * ("comercio") o por nombre ("Comercio").
 */
class Niveles
{
    /**
     * @return array<string, array{nombre: string, desde: int}>
     */
    public static function todos(): array
    {
        return config('tienda.niveles');
    }

    /**
     * @return list<string>
     */
    public static function claves(): array
    {
        return array_keys(self::todos());
    }

    public static function nombre(string $clave): string
    {
        return self::todos()[$clave]['nombre'] ?? ucfirst($clave);
    }

    public static function clave(string $nombre): string
    {
        return collect(self::todos())->search(fn (array $nivel) => $nivel['nombre'] === $nombre) ?: 'inicial';
    }

    /**
     * Nivel que corresponde a un pedido: el de la cantidad de cajas, o el del
     * cliente si tiene uno asignado más alto.
     */
    public static function paraCajas(int $cajas, ?string $minimo = null): string
    {
        $claves = self::claves();
        $porCajas = collect(self::todos())->filter(fn (array $nivel) => $nivel['desde'] <= $cajas)->keys()->last() ?? $claves[0];

        if ($minimo === null || ! in_array($minimo, $claves, true)) {
            return $porCajas;
        }

        return array_search($minimo, $claves, true) > array_search($porCajas, $claves, true) ? $minimo : $porCajas;
    }

    /**
     * Si hay precio para todos los niveles: si no, el producto no se puede pedir,
     * porque el nivel depende de las cajas del pedido.
     *
     * @param  array<string, string>  $precios  clave de nivel => precio
     */
    public static function completos(array $precios): bool
    {
        return array_diff(self::claves(), array_keys($precios)) === [];
    }

    /**
     * Siguiente nivel, o null si ya es el más alto.
     */
    public static function siguiente(string $clave): ?string
    {
        $claves = self::claves();
        $posicion = array_search($clave, $claves, true);

        return $posicion === false ? null : ($claves[$posicion + 1] ?? null);
    }

    /**
     * Clases del badge del nivel.
     */
    public static function clases(string $clave): string
    {
        return match ($clave) {
            'distribuidor' => 'bg-carbon text-white',
            'comercio' => 'bg-queso-claro text-carbon',
            default => 'bg-arena text-carbon',
        };
    }
}
