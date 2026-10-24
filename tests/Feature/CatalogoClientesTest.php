<?php

use App\Enums\EstadoCliente;
use App\Models\Customer;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::firstWhere('email', 'admin@masaprima.com');
    $this->actingAs($this->admin);
});

test('las pantallas de clientes y precios cargan', function () {
    $producto = Product::firstWhere('codigo', 'PZ-03');
    $cliente = Customer::firstWhere('cuit', '30123456789');

    $this->get(route('admin.clientes'))->assertOk()->assertSee('Panadería San Cayetano');
    $this->get(route('admin.cliente', $cliente))->assertOk()->assertSee('Entrada por portón lateral');
    $this->get(route('admin.precios'))->assertOk()->assertSee('Pizza Muzzarella');
    $this->get(route('admin.producto', $producto))->assertOk()->assertSee('Precios por caja');
    $this->get(route('admin.producto.nuevo'))->assertOk();
});

test('los cambios de precio quedan en borrador hasta publicarlos', function () {
    $producto = Product::firstWhere('codigo', 'PZ-03');

    Livewire::test('pages::admin.precios')
        ->call('fijar', $producto->id, 'inicial', '12.000')
        ->call('fijar', $producto->id, 'comercio', '11000')
        ->assertSee('Tenés 2 precios modificados sin publicar');

    expect(PriceList::vigente())->toBeNull()
        ->and(PriceList::borrador()->mapa()[$producto->id])->toEqual(['inicial' => '12000.00', 'comercio' => '11000.00']);

    PriceList::borrador()->update(['vigente_desde' => today()]);

    Livewire::test('pages::admin.precios')->call('publicar');

    expect(PriceList::vigente()->numero)->toBe(1)
        ->and(PriceList::borrador())->toBeNull();
});

test('el aumento general se calcula sobre la lista vigente y redondea', function () {
    $producto = Product::firstWhere('codigo', 'PZ-01');
    $lista = PriceList::create(['numero' => 1, 'vigente_desde' => today()->subWeek(), 'publicada_at' => now()]);
    $lista->fijar($producto, 'inicial', 10000);
    $lista->fijar($producto, 'comercio', 9030);

    $prueba = Livewire::test('pages::admin.precios')
        ->set('aplicarA', 'todo')
        ->set('porcentaje', '8')
        ->set('redondeo', 100)
        ->set('niveles', ['inicial', 'comercio'])
        ->call('aplicarAumento')
        ->call('aplicarAumento');

    expect(PriceList::borrador()->mapa()[$producto->id])->toEqual(['inicial' => '10800.00', 'comercio' => '9800.00']);
    $prueba->assertSee('+8 %');
});

test('la tienda cobra el precio vigente del nivel del cliente', function () {
    $producto = Product::firstWhere('codigo', 'PZ-01');
    $lista = PriceList::create(['numero' => 1, 'vigente_desde' => today(), 'publicada_at' => now()]);
    $lista->fijar($producto, 'inicial', 10000);
    $lista->fijar($producto, 'comercio', 9000);
    $lista->fijar($producto, 'distribuidor', 8000);

    $usuario = User::factory()->create();
    Customer::factory()->for($usuario)->create(['nivel' => 'distribuidor']);

    $this->actingAs($usuario);

    Livewire::test('pages::catalogo')->assertSee('$ 8.000');
});

test('un producto pausado no aparece en la tienda', function () {
    $producto = Product::firstWhere('codigo', 'PZ-02');

    Livewire::test('pages::admin.precios')->call('alternarActivo', $producto->id);

    expect($producto->fresh()->activo)->toBeFalse();
    Livewire::test('pages::catalogo')->assertDontSee('Pizza Jamón y Morrón');
});

test('se edita un producto y se guardan sus precios en el borrador', function () {
    $producto = Product::firstWhere('codigo', 'PZ-03');

    Livewire::test('pages::admin.producto', ['product' => $producto])
        ->set('nombre', 'Pizza Muzzarella grande')
        ->set('unidades', '6')
        ->set('tamano', '35')
        ->set('precios.inicial', '15.500')
        ->call('guardar')
        ->assertRedirect(route('admin.precios'));

    $producto->refresh();
    expect($producto->nombre)->toBe('Pizza Muzzarella grande')
        ->and($producto->detalle)->toBe('Caja x 6 unid. · 35 cm')
        ->and($producto->actualizado_por)->toBe('Laura')
        ->and(PriceList::borrador()->mapa()[$producto->id])->toEqual(['inicial' => '15500.00']);
});

test('se crea un producto nuevo', function () {
    Livewire::test('pages::admin.producto')
        ->set('nombre', 'Pizza Fugazzeta')
        ->set('codigo', 'pz-06')
        ->call('guardar')
        ->assertHasNoErrors();

    expect(Product::firstWhere('codigo', 'PZ-06'))->not->toBeNull();
});

test('las altas pendientes se aprueban o rechazan', function () {
    $pendiente = Customer::firstWhere('estado', EstadoCliente::Pendiente);
    $otra = Customer::factory()->create(['estado' => EstadoCliente::Pendiente]);

    Livewire::test('pages::admin.clientes')
        ->assertSee('Aprobar alta')
        ->call('aprobar', $pendiente->id)
        ->call('rechazar', $otra->id);

    expect($pendiente->fresh()->estado)->toBe(EstadoCliente::Activo)
        ->and($pendiente->fresh()->aprobado_at)->not->toBeNull()
        ->and($otra->fresh()->estado)->toBe(EstadoCliente::Rechazado);
});

test('los filtros de clientes separan activos y sin comprar', function () {
    Livewire::test('pages::admin.clientes')
        ->set('vista', 'sin_comprar')
        ->assertSee('Bar El Cruce')
        ->assertDontSee('Autoservicio Don Pepe')
        ->set('vista', 'activos')
        ->assertSee('Autoservicio Don Pepe')
        ->assertDontSee('Bar El Cruce');
});

test('se carga un cliente a mano', function () {
    Livewire::test('pages::admin.clientes')
        ->set('razonSocial', 'Pizzería Nueva')
        ->set('cuit', '20-12345678-6')
        ->call('cargar')
        ->assertHasNoErrors();

    expect(Customer::firstWhere('razon_social', 'Pizzería Nueva')->estado)->toBe(EstadoCliente::Activo);
});

test('la ficha cambia las condiciones del cliente', function () {
    $cliente = Customer::firstWhere('cuit', '30123456789');

    Livewire::test('pages::admin.cliente', ['customer' => $cliente])
        ->set('nivel', 'distribuidor')
        ->call('alternarCuentaCorriente')
        ->call('editar')
        ->set('datos.responsable', 'Carlos Pérez')
        ->call('guardarDatos');

    $cliente->refresh();
    expect($cliente->nivel)->toBe('distribuidor')
        ->and($cliente->cuenta_corriente_dias)->toBe(30)
        ->and($cliente->responsable)->toBe('Carlos Pérez');
});
