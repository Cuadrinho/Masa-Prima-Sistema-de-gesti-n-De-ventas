<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view('/', 'inicio')->name('home');

Route::livewire('catalogo', 'pages::catalogo')->name('catalogo');
Route::livewire('pedido', 'pages::pedido')->middleware('auth')->name('pedido');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', fn (Request $request) => $request->user()->esPersonal()
        ? redirect()->route('admin.panel')
        : view('dashboard'))->name('dashboard');
});

Route::middleware(['auth', 'personal'])->prefix('admin')->name('admin.')->group(function () {
    Route::livewire('/', 'pages::admin.panel')->name('panel');
    Route::livewire('pedidos', 'pages::admin.pedidos')->name('pedidos');
    Route::livewire('pedidos/{order}', 'pages::admin.pedido')->name('pedido');
    Route::livewire('cocina', 'pages::admin.cocina')->name('cocina');
    Route::livewire('entregas', 'pages::admin.entregas')->name('entregas');
    Route::livewire('finalizados', 'pages::admin.finalizados')->name('finalizados');
});

require __DIR__.'/settings.php';
