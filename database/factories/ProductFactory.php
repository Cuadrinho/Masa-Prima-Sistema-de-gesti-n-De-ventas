<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nombre = fake()->unique()->words(2, true);

        return [
            'codigo' => strtoupper(fake()->unique()->bothify('??-##')),
            'nombre' => ucfirst($nombre),
            'nombre_corto' => ucfirst(strtok($nombre, ' ')),
            'categoria' => fake()->randomElement(['prepizzas', 'precocidas', 'congeladas', 'masas']),
            'detalle' => 'Caja x [N] unid. · [TAMAÑO] cm',
            'ilustracion' => 'prepizza',
            'activo' => true,
            'orden' => 0,
        ];
    }
}
