<?php

use App\Enums\EstadoPedido;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\ProductSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(ProductSeeder::class);
});

test('la home se muestra', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee(config('tienda.nombre'))
        ->assertSee('Pizzas prelistas, listas para tu horno.');
});

test('el catálogo lista las pizzas con foto y permite buscar', function () {
    $this->get(route('catalogo'))
        ->assertOk()
        ->assertSee(['Pizza Calabresa', 'Pizza Jamón y Morrón', 'Pizza Muzzarella', 'Pizza Muzzarella con Huevo', 'Pizza Napolitana'])
        ->assertSee('images/productos/pizza-calabresa.webp');

    Livewire::test('pages::catalogo')
        ->set('buscar', 'huevo')
        ->assertSee('Pizza Muzzarella con Huevo')
        ->assertDontSee('Pizza Napolitana');
});

test('se pueden sumar y restar cajas del carrito', function () {
    Livewire::test('pages::catalogo')
        ->call('sumar', 'PZ-01')
        ->call('sumar', 'PZ-01')
        ->call('sumar', 'PZ-03')
        ->call('restar', 'PZ-03')
        ->assertSet('carrito', ['PZ-01' => 2]);

    expect(session('carrito'))->toBe(['PZ-01' => 2]);
});

test('ignora códigos de producto inexistentes', function () {
    Livewire::test('pages::catalogo')
        ->call('sumar', 'NO-EXISTE')
        ->assertSet('carrito', []);
});

test('el checkout pide iniciar sesión y conserva el carrito', function () {
    $this->withSession(['carrito' => ['PZ-01' => 5]])
        ->get(route('pedido'))
        ->assertRedirect(route('login'));

    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('pedido'));

    expect(session('carrito'))->toBe(['PZ-01' => 5]);
});

test('el checkout exige el pedido mínimo', function () {
    $this->actingAs(User::factory()->create());
    session(['carrito' => ['PZ-01' => 2]]);

    Livewire::test('pages::pedido')->assertRedirect(route('catalogo'));
});

test('el checkout muestra el resumen del carrito', function () {
    $this->actingAs(User::factory()->create());
    session(['carrito' => ['PZ-01' => 4, 'PZ-03' => 8]]);

    Livewire::test('pages::pedido')
        ->assertNoRedirect()
        ->assertSee('4 × Pizza Calabresa')
        ->assertSee('12 cajas · Nivel Comercio aplicado');
});

test('el primer pedido crea el comercio de la cuenta', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    session(['carrito' => ['PZ-01' => 4, 'PZ-03' => 8]]);

    Livewire::test('pages::pedido')
        ->assertSee('Es tu primer pedido')
        ->set('razonSocial', 'Pizzería Nueva')
        ->set('cuit', '20-12345678-6')
        ->set('direccion', 'Av. Siempreviva 742')
        ->set('franja', 'tarde')
        ->call('confirmar')
        ->assertHasNoErrors()
        ->assertSee('¡Recibimos tu pedido!');

    $order = Order::with('items', 'customer', 'events')->sole();

    expect($order->estado)->toBe(EstadoPedido::Solicitud)
        ->and($order->cajas())->toBe(12)
        ->and($order->nivel)->toBe('Comercio')
        ->and($order->customer->cuit)->toBe('20123456786')
        ->and($order->customer->user_id)->toBe($user->id)
        ->and($order->events)->toHaveCount(1)
        ->and(session('carrito'))->toBeNull();
});

test('los pedidos siguientes usan el comercio de la cuenta', function () {
    $user = User::factory()->create();
    $cliente = Customer::factory()->for($user)->create(['razon_social' => 'Pizzería Don Carlos', 'cuit' => '20123456786']);
    $cliente->orders()->create([
        'estado' => EstadoPedido::Finalizado, 'entrega_tipo' => 'reparto', 'entrega_fecha' => today()->subWeek(),
        'franja' => 'tarde', 'direccion' => 'Av. Siempreviva 742', 'metodo_pago' => 'transferencia', 'nivel' => 'Inicial',
    ]);
    $this->actingAs($user);
    session(['carrito' => ['PZ-01' => 5]]);

    Livewire::test('pages::pedido')
        ->assertSee('Pizzería Don Carlos')
        ->assertSee('20-12345678-6')
        ->assertDontSee('Es tu primer pedido')
        ->assertSet('direccion', 'Av. Siempreviva 742')
        ->assertSet('franja', 'tarde')
        ->set('razonSocial', 'Otro nombre')
        ->call('confirmar')
        ->assertHasNoErrors();

    expect(Customer::count())->toBe(1)
        ->and($cliente->orders()->count())->toBe(2)
        ->and($cliente->fresh()->razon_social)->toBe('Pizzería Don Carlos');
});

test('no se puede usar el CUIT de otro comercio', function () {
    Customer::factory()->create(['cuit' => '20123456786']);
    $this->actingAs(User::factory()->create());
    session(['carrito' => ['PZ-01' => 5]]);

    Livewire::test('pages::pedido')
        ->set('razonSocial', 'Pizzería Nueva')
        ->set('cuit', '20-12345678-6')
        ->set('entrega', 'retiro')
        ->call('confirmar')
        ->assertHasErrors('cuit');

    expect(Order::count())->toBe(0);
});

test('el checkout valida el CUIT y la dirección', function () {
    $this->actingAs(User::factory()->create());
    session(['carrito' => ['PZ-01' => 5]]);

    Livewire::test('pages::pedido')
        ->set('razonSocial', 'Pizzería Nueva')
        ->set('cuit', '30-12345678-9')
        ->call('confirmar')
        ->assertHasErrors(['cuit', 'direccion']);

    expect(Order::count())->toBe(0);
});

test('la cuenta corriente es solo para comercios aprobados', function () {
    $this->actingAs(User::factory()->create());
    session(['carrito' => ['PZ-01' => 5]]);

    Livewire::test('pages::pedido')
        ->set('razonSocial', 'Pizzería Nueva')
        ->set('cuit', '20123456786')
        ->set('entrega', 'retiro')
        ->set('pago', 'cuenta_corriente')
        ->call('confirmar')
        ->assertHasErrors('pago');

    $aprobado = User::factory()->create();
    Customer::factory()->for($aprobado)->conCuentaCorriente()->create();
    $this->actingAs($aprobado);
    session(['carrito' => ['PZ-01' => 5]]);

    Livewire::test('pages::pedido')
        ->set('entrega', 'retiro')
        ->set('pago', 'cuenta_corriente')
        ->call('confirmar')
        ->assertHasNoErrors();

    expect(Order::count())->toBe(1);
});
