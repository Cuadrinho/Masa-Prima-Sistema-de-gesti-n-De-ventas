<?php

/*
|--------------------------------------------------------------------------
| Datos de la tienda
|--------------------------------------------------------------------------
|
| Los productos viven en la tabla `products` (ver ProductSeeder). Los
| niveles de precio quedan acá hasta tener la pantalla de precios.
|
*/

return [

    'nombre' => 'Masa Prima',

    'bajada' => 'Pizzas al por mayor',

    'pedido_minimo' => 5,

    // Cajas totales del pedido a partir de las cuales aplica cada nivel.
    'niveles' => [
        'inicial' => ['nombre' => 'Inicial', 'desde' => 0],
        'comercio' => ['nombre' => 'Comercio', 'desde' => 10],
        'distribuidor' => ['nombre' => 'Distribuidor', 'desde' => 50],
    ],

    'categorias' => [
        'pizzas' => 'Pizzas',
    ],

];
