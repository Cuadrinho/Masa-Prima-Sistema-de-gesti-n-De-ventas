<?php

use App\Models\Customer;
use App\Models\User;

test('la pantalla de ingreso muestra el portal de clientes', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Pedí')
        ->assertSee('CUIT o email')
        ->assertSee('Solicitar alta');
});

test('se puede entrar con el CUIT del comercio, con o sin guiones', function (string $cuit) {
    $user = User::factory()->create();
    Customer::factory()->for($user)->create(['cuit' => '20123456786']);

    $this->post(route('login.store'), ['email' => $cuit, 'password' => 'password'])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
})->with(['20123456786', '20-12345678-6']);

test('se sigue pudiendo entrar con el email', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

test('un CUIT sin cuenta o con contraseña incorrecta no entra', function () {
    $user = User::factory()->create();
    Customer::factory()->for($user)->create(['cuit' => '20123456786']);
    Customer::factory()->create(['cuit' => '30711111111']);

    $this->post(route('login.store'), ['email' => '20-12345678-6', 'password' => 'otra'])
        ->assertSessionHasErrors('email');

    $this->post(route('login.store'), ['email' => '30711111111', 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});
