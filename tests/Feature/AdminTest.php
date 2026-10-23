<?php

use App\Enums\EstadoPedido;
use App\Enums\Rol;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::firstWhere('email', 'admin@masaprima.com');
});

test('los clientes no pueden entrar al panel', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.panel'))
        ->assertForbidden();
});

test('los invitados van al login', function () {
    $this->get(route('admin.panel'))->assertRedirect(route('login'));
});

test('el personal entra desde el dashboard al panel', function () {
    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertRedirect(route('admin.panel'));
});

test('todas las pantallas del panel cargan', function (string $ruta) {
    $this->actingAs($this->admin)
        ->get(route($ruta, $ruta === 'admin.pedido' ? 1052 : []))
        ->assertOk();
})->with(['admin.panel', 'admin.pedidos', 'admin.pedido', 'admin.cocina', 'admin.entregas', 'admin.finalizados']);

test('la cocina también puede entrar al panel', function () {
    $this->actingAs(User::factory()->personal(Rol::Cocina)->create())
        ->get(route('admin.cocina'))
        ->assertOk();
});

test('el panel muestra las solicitudes y permite aceptarlas', function () {
    $this->actingAs($this->admin);

    Livewire::test('pages::admin.panel')
        ->assertSee('Rotisería La Esquina')
        ->call('aceptar', 1055);

    $order = Order::find(1055);
    expect($order->estado)->toBe(EstadoPedido::PorPreparar)
        ->and($order->aceptado_at)->not->toBeNull()
        ->and($order->events()->first()->autor)->toBe('Laura (admin)');
});

test('rechazar solo aplica a solicitudes', function () {
    $this->actingAs($this->admin);

    Livewire::test('pages::admin.panel')
        ->call('rechazar', 1054)
        ->call('rechazar', 1052);

    expect(Order::find(1054)->estado)->toBe(EstadoPedido::Rechazado)
        ->and(Order::find(1052)->estado)->toBe(EstadoPedido::PorPreparar);
});

test('un pedido sin pagar no se puede finalizar', function () {
    $order = Order::find(1048);

    $order->avanzar('Laura (admin)');
    expect($order->fresh()->estado)->toBe(EstadoPedido::Entregado);

    $order->registrarPago('Laura (admin)');
    $order->avanzar('Laura (admin)');
    expect($order->fresh()->estado)->toBe(EstadoPedido::Finalizado);
});

test('la cocina ve las solicitudes por aprobar como cajas a confirmar', function () {
    $this->actingAs($this->admin);

    Livewire::test('pages::admin.cocina', ['dia' => 'manana'])
        ->assertSee('Por aprobar · 2 solicitudes')
        ->assertSee('+26 cajas si se aceptan')
        ->assertSee('#1055');
});

test('al aceptar una solicitud entra a la producción de su día', function () {
    $this->actingAs($this->admin);

    $cocina = Livewire::test('pages::admin.cocina', ['dia' => 'manana']);
    expect($cocina->get('comandas'))->toHaveCount(0);

    Order::find(1055)->avanzar('Laura (admin)');

    expect(Livewire::test('pages::admin.cocina', ['dia' => 'manana'])->get('comandas')->pluck('id')->all())->toBe([1055]);
});

test('los pedidos de los próximos días también llegan a cocina', function () {
    Order::find(1055)->update(['entrega_fecha' => today()->addDays(3)]);
    Order::find(1055)->avanzar('Laura (admin)');

    $this->actingAs($this->admin);

    expect(Livewire::test('pages::admin.cocina', ['dia' => 'proximos'])->get('comandas')->pluck('id')->all())->toBe([1055]);
});

test('el detalle de una solicitud muestra su impacto en cocina', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.pedido', 1055))
        ->assertOk()
        ->assertSee('Impacto en cocina')
        ->assertSee('si lo aceptás, pasa de');
});
