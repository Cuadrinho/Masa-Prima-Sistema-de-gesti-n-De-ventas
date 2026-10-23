<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'razon_social' => fake()->company(),
            'cuit' => fake()->unique()->numerify('30#########'),
            'tipo_comercio' => 'Pizzería',
            'localidad' => 'Laferrere',
            'whatsapp' => fake()->numerify('11 ####-####'),
            'email' => fake()->safeEmail(),
            'condicion_iva' => 'A',
        ];
    }

    public function conCuentaCorriente(int $dias = 30): static
    {
        return $this->state(fn (array $attributes) => [
            'cuenta_corriente_dias' => $dias,
        ]);
    }
}
