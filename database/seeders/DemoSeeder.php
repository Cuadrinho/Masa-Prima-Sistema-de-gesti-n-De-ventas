<?php

namespace Database\Seeders;

use App\Enums\EstadoPedido;
use App\Enums\Franja;
use App\Enums\MetodoPago;
use App\Enums\Rol;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Datos de demostración para el panel interno (los del boceto), con fechas
 * relativas al día de hoy. Solo para desarrollo.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstWhere('email', 'admin@masaprima.com')
            ?? User::factory()->personal(Rol::Admin)->create(['name' => 'Laura', 'email' => 'admin@masaprima.com']);

        if (! User::where('email', 'martin@masaprima.test')->exists()) {
            User::factory()->personal(Rol::Cocina)->create(['name' => 'Martín', 'email' => 'martin@masaprima.test']);
        }

        if (Order::query()->exists()) {
            $this->command?->warn('Ya hay pedidos cargados: no se agregan pedidos de demostración.');

            return;
        }

        $clientes = collect([
            'esquina' => ['Rotisería La Esquina', '30711111111', 'Rotisería', 'Isidro Casanova', null],
            'club' => ['Club Atl. Villa Luzuriaga', '30722222222', 'Club', 'Villa Luzuriaga', 30],
            'hermanos' => ['Almacén Los Hermanos', '30733333333', 'Almacén', 'San Justo', null],
            'carlos' => ['Pizzería Don Carlos S.R.L.', '30123456789', 'Pizzería', 'Laferrere', null],
            'sabores' => ['Catering Sabores', '30744444444', 'Catering', 'Morón', null],
            'pepe' => ['Autoservicio Don Pepe', '30755555555', 'Autoservicio', 'Ramos Mejía', null],
            'comedor' => ['Comedor Escolar N.º 12', '30766666666', 'Comedor', 'González Catán', 30],
            'fortin' => ['Pizzería El Fortín', '30777777777', 'Pizzería', 'Laferrere', null],
            'quinta' => ['Eventos La Quinta', '30788888888', 'Eventos', 'Ituzaingó', 30],
            'mili' => ['Kiosco y Almacén Mili', '30799999999', 'Almacén', 'Gregorio de Laferrere', null],
        ])->map(fn (array $c) => Customer::create([
            'razon_social' => $c[0],
            'cuit' => $c[1],
            'tipo_comercio' => $c[2],
            'localidad' => $c[3],
            'cuenta_corriente_dias' => $c[4],
            'condicion_iva' => 'A',
        ]));

        $productos = Product::all()->keyBy('codigo');

        // [número, cliente, días atrás, hora, estado, entrega (días desde hoy), franja, pago, pagado, ítems]
        $pedidos = [
            [1055, 'esquina', 0, '13:58', EstadoPedido::Solicitud, 1, Franja::Tarde, MetodoPago::Transferencia, false, ['PZ-05' => 3, 'PZ-04' => 3]],
            [1054, 'club', 0, '12:31', EstadoPedido::Solicitud, 1, Franja::Manana, MetodoPago::CuentaCorriente, false, ['PZ-03' => 10, 'PZ-05' => 6, 'PZ-01' => 4]],
            [1053, 'hermanos', 0, '10:12', EstadoPedido::PorPreparar, 0, Franja::Tarde, MetodoPago::MercadoPago, true, ['PZ-01' => 5, 'PZ-05' => 3]],
            [1052, 'carlos', 0, '08:47', EstadoPedido::PorPreparar, 0, Franja::Tarde, MetodoPago::Transferencia, false, ['PZ-01' => 4, 'PZ-03' => 6, 'PZ-04' => 2]],
            [1051, 'sabores', 1, '19:20', EstadoPedido::EnHorno, 0, Franja::Tarde, MetodoPago::MercadoPago, true, ['PZ-03' => 4, 'PZ-05' => 5]],
            [1050, 'pepe', 1, '16:05', EstadoPedido::Listo, 0, Franja::Tarde, MetodoPago::Transferencia, true, ['PZ-01' => 10, 'PZ-02' => 10, 'PZ-03' => 5]],
            [1049, 'comedor', 1, '11:40', EstadoPedido::EnReparto, 0, Franja::Manana, MetodoPago::CuentaCorriente, false, ['PZ-02' => 10, 'PZ-05' => 5]],
            [1048, 'fortin', 1, '09:15', EstadoPedido::Entregado, 0, Franja::Manana, MetodoPago::Transferencia, false, ['PZ-01' => 8, 'PZ-03' => 6, 'PZ-04' => 4]],
            [1047, 'quinta', 3, '10:30', EstadoPedido::Entregado, -2, Franja::Manana, MetodoPago::CuentaCorriente, false, ['PZ-01' => 10, 'PZ-03' => 8]],
            [1046, 'mili', 3, '17:30', EstadoPedido::Finalizado, -2, Franja::Tarde, MetodoPago::MercadoPago, true, ['PZ-01' => 3, 'PZ-02' => 2]],
            [1045, 'carlos', 5, '13:10', EstadoPedido::Finalizado, -4, Franja::Tarde, MetodoPago::Transferencia, true, ['PZ-01' => 4, 'PZ-03' => 6, 'PZ-04' => 2]],
            [1044, 'pepe', 6, '19:45', EstadoPedido::Finalizado, -5, Franja::Tarde, MetodoPago::Transferencia, true, ['PZ-01' => 12, 'PZ-02' => 8]],
            [1043, 'comedor', 6, '15:02', EstadoPedido::Finalizado, -5, Franja::Manana, MetodoPago::CuentaCorriente, true, ['PZ-02' => 10, 'PZ-05' => 5]],
            [1042, 'esquina', 7, '18:20', EstadoPedido::Finalizado, -6, Franja::Tarde, MetodoPago::MercadoPago, true, ['PZ-05' => 4, 'PZ-04' => 2]],
            [1041, 'fortin', 7, '12:40', EstadoPedido::Finalizado, -6, Franja::Manana, MetodoPago::Transferencia, true, ['PZ-01' => 8, 'PZ-03' => 8]],
            [1040, 'hermanos', 7, '18:05', EstadoPedido::Finalizado, -6, Franja::Tarde, MetodoPago::MercadoPago, true, ['PZ-01' => 4, 'PZ-05' => 3]],
            [1039, 'mili', 12, '11:15', EstadoPedido::Finalizado, -11, Franja::Manana, MetodoPago::MercadoPago, true, ['PZ-01' => 2, 'PZ-03' => 3]],
        ];

        $ahora = CarbonImmutable::now();

        foreach ($pedidos as [$numero, $clave, $diasAtras, $hora, $estado, $entrega, $franja, $pago, $pagado, $items]) {
            $cliente = $clientes[$clave];
            $creado = CarbonImmutable::today()->subDays($diasAtras)->setTimeFromTimeString($hora);
            $entregaFecha = CarbonImmutable::today()->addDays($entrega);
            $cajas = array_sum($items);

            $order = new Order([
                'customer_id' => $cliente->id,
                'estado' => $estado,
                'entrega_tipo' => 'reparto',
                'entrega_fecha' => $entregaFecha,
                'franja' => $franja,
                'direccion' => 'Av. [CALLE] 1234, '.$cliente->localidad,
                'indicaciones' => $numero === 1052 ? 'Entrada por portón lateral, preguntar por Carlos.' : null,
                'camion' => $entrega === 0 ? 'Camión 1 · Ruta oeste' : null,
                'metodo_pago' => $pago,
                'nivel' => Order::nivelPara($cajas),
            ]);
            $order->id = $numero;
            $order->created_at = $creado;
            $order->updated_at = $creado;

            $eventos = [
                [$creado, $cliente->razon_social, "Realizó el pedido online · {$cajas} cajas · Nivel {$order->nivel}."],
                [$creado->addMinutes(2), 'Sistema', 'Se envió confirmación por email y WhatsApp.'],
            ];

            // Fechas de cada estado alcanzado: aceptado a los 15 minutos y el resto el día de entrega.
            foreach (EstadoPedido::circuito() as $i => $paso) {
                if ($i === 0 || $i > array_search($estado, EstadoPedido::circuito(), true)) {
                    continue;
                }

                $fecha = $paso === EstadoPedido::PorPreparar
                    ? $creado->addMinutes(15)
                    : $entregaFecha->setTime($franja === Franja::Manana ? 7 : 12, 0)->addMinutes(40 * $i);
                $fecha = $fecha->min($ahora);

                $order->{$paso->columnaFecha()} = $fecha;
                $autor = in_array($paso, [EstadoPedido::EnHorno, EstadoPedido::Listo]) ? 'Martín (cocina)' : "{$admin->name} (admin)";
                $eventos[] = [$fecha, $autor, $paso->actividad()];
            }

            if ($pagado) {
                $order->pagado_at = ($order->entregado_at ?? $creado->addMinutes(30))->min($ahora);
                $eventos[] = [$order->pagado_at, "{$admin->name} (admin)", 'Registró el pago.'];
            }

            $order->save();

            foreach ($items as $codigo => $cantidad) {
                $order->items()->create([
                    'product_id' => $productos[$codigo]->id,
                    'cajas' => $cantidad,
                    'listo' => in_array($estado, [EstadoPedido::Listo, EstadoPedido::EnReparto, EstadoPedido::Entregado, EstadoPedido::Finalizado])
                        || ($numero === 1051 && $codigo === 'PZ-03'),
                ]);
            }

            foreach ($eventos as [$fecha, $autor, $descripcion]) {
                $order->events()->create(['autor' => $autor, 'descripcion' => $descripcion, 'created_at' => $fecha]);
            }
        }

        // Los ids se cargaron a mano: la secuencia de Postgres sigue desde el último.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SELECT setval(pg_get_serial_sequence('orders', 'id'), (SELECT MAX(id) FROM orders))");
        }
    }
}
