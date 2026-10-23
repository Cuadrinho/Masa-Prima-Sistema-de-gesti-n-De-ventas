<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * CUIT/CUIL argentino: 11 dígitos con dígito verificador válido.
 */
class Cuit implements ValidationRule
{
    public static function normalizar(string $cuit): string
    {
        return preg_replace('/\D/', '', $cuit);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $cuit = self::normalizar((string) $value);

        if (strlen($cuit) !== 11) {
            $fail('El CUIT debe tener 11 dígitos.');

            return;
        }

        $suma = 0;

        foreach ([5, 4, 3, 2, 7, 6, 5, 4, 3, 2] as $i => $peso) {
            $suma += (int) $cuit[$i] * $peso;
        }

        $verificador = 11 - ($suma % 11);
        $verificador = $verificador === 11 ? 0 : $verificador;

        if ($verificador === 10 || $verificador !== (int) $cuit[10]) {
            $fail('El CUIT no es válido. Revisá los números.');
        }
    }
}
