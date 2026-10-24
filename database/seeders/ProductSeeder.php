<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Catálogo inicial. Los precios quedan vacíos hasta cargar los reales.
     */
    public function run(): void
    {
        $productos = [
            ['PZ-01', 'Pizza Calabresa', 'Calabresa', 'calabresa', 'pizza-calabresa.webp'],
            ['PZ-02', 'Pizza Jamón y Morrón', 'Jamón y morrón', 'especial', 'pizza-jamon-morron.webp'],
            ['PZ-03', 'Pizza Muzzarella', 'Muzzarella', 'muzzarella', 'pizza-muzzarella.webp'],
            ['PZ-04', 'Pizza Muzzarella con Huevo', 'Muzza c/ huevo', 'muzzarella', 'pizza-muzzarella-huevo.webp'],
            ['PZ-05', 'Pizza Napolitana', 'Napolitana', 'napolitana', 'pizza-napolitana.webp'],
        ];

        foreach ($productos as $orden => [$codigo, $nombre, $corto, $ilustracion, $foto]) {
            Product::updateOrCreate(['codigo' => $codigo], [
                'nombre' => $nombre,
                'nombre_corto' => $corto,
                'categoria' => 'pizzas',
                'detalle' => null,
                'ilustracion' => $ilustracion,
                'imagen' => 'images/productos/'.$foto,
                'orden' => $orden + 1,
            ]);
        }
    }
}
