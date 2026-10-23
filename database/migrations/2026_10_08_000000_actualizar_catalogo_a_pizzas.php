<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El catálogo pasa a ser una sola línea de 5 pizzas con foto.
 *
 * Los productos anteriores se convierten en las pizzas nuevas y los que
 * sobran se reemplazan en los pedidos existentes antes de borrarlos.
 */
return new class extends Migration
{
    /**
     * Código anterior => [código nuevo, nombre, nombre corto, ilustración, foto, orden].
     */
    private const PIZZAS = [
        'CG-01' => ['PZ-01', 'Pizza Calabresa', 'Calabresa', 'calabresa', 'pizza-calabresa.webp', 1],
        'CG-02' => ['PZ-02', 'Pizza Jamón y Morrón', 'Jamón y morrón', 'especial', 'pizza-jamon-morron.webp', 2],
        'PC-01' => ['PZ-03', 'Pizza Muzzarella', 'Muzzarella', 'muzzarella', 'pizza-muzzarella.webp', 3],
        'PC-03' => ['PZ-04', 'Pizza Muzzarella con Huevo', 'Muzza c/ huevo', 'muzzarella', 'pizza-muzzarella-huevo.webp', 4],
        'PC-02' => ['PZ-05', 'Pizza Napolitana', 'Napolitana', 'napolitana', 'pizza-napolitana.webp', 5],
    ];

    /**
     * Productos que salen del catálogo => código de la pizza que los reemplaza en los pedidos.
     */
    private const REEMPLAZOS = [
        'PP-01' => 'PZ-01',
        'PP-02' => 'PZ-02',
        'MS-01' => 'PZ-05',
    ];

    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('imagen')->nullable()->after('ilustracion');
        });

        DB::transaction(function () {
            foreach (self::PIZZAS as $anterior => [$codigo, $nombre, $corto, $ilustracion, $foto, $orden]) {
                DB::table('products')->where('codigo', $anterior)->update([
                    'codigo' => $codigo,
                    'nombre' => $nombre,
                    'nombre_corto' => $corto,
                    'categoria' => 'pizzas',
                    'ilustracion' => $ilustracion,
                    'imagen' => 'images/productos/'.$foto,
                    'orden' => $orden,
                    'updated_at' => now(),
                ]);
            }

            foreach (self::REEMPLAZOS as $anterior => $nuevo) {
                $viejo = DB::table('products')->where('codigo', $anterior)->value('id');
                $reemplazo = DB::table('products')->where('codigo', $nuevo)->value('id');

                if ($viejo === null) {
                    continue;
                }

                if ($reemplazo !== null) {
                    DB::table('order_items')->where('product_id', $viejo)->update(['product_id' => $reemplazo]);
                }

                if (! DB::table('order_items')->where('product_id', $viejo)->exists()) {
                    DB::table('products')->where('id', $viejo)->delete();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('imagen');
        });
    }
};
