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

    // Los precios de las listas no incluyen IVA: se suma aparte con esta alícuota.
    'iva' => 0.21,

    // Costo del reparto a domicilio. El retiro por fábrica siempre es sin cargo.
    'envio' => 0,

    // Plazo de cuenta corriente para los clientes que no tienen uno propio.
    'cuenta_corriente_dias' => 30,

    // Datos del negocio que se muestran en la web. Los que quedan en null no se muestran.
    'empresa' => [
        'razon_social' => null,
        'cuit' => null,
        'direccion' => null, // de la fábrica, para el retiro
        'whatsapp' => null,
        'email' => null,
    ],

    // "La Matanza"
    'zona_reparto' => null,

    // Días => horario, por ejemplo ['Mar a Vie' => '8:00 a 17:00', 'Sábados' => '8:00 a 13:00'].
    'horarios' => [],

    // Plazos que se prometen en la home, en horas (hábiles para el alta).
    'entrega_horas' => 24,
    'alta_horas' => null,

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
