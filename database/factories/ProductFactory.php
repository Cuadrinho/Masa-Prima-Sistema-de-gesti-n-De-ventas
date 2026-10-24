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
        $palabra = fake()->word();

        return [
            'codigo' => strtoupper(fake()->unique()->bothify('??-##')),
            'nombre' => ucfirst($palabra.' '.fake()->word()),
            'nombre_corto' => ucfirst($palabra),
            'categoria' => fake()->randomElement(['prepizzas', 'precocidas', 'congeladas', 'masas']),
            'detalle' => null,
            'ilustracion' => 'prepizza',
            'activo' => true,
            'orden' => 0,
        ];
    }
}
