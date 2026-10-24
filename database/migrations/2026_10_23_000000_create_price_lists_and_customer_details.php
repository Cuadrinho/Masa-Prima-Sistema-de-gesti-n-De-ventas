<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pantallas de catálogo y precios y de clientes.
 *
 * Los precios pasan de una columna en `products` a listas numeradas con un
 * precio por nivel. La lista sin publicar es el borrador que se edita.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_lists', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('numero')->nullable()->unique();
            $table->date('vigente_desde');
            $table->timestamp('publicada_at')->nullable()->index();
            $table->string('autor')->nullable();
            $table->timestamps();
        });

        Schema::create('prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('nivel');
            $table->decimal('precio', 12, 2);
            $table->timestamps();

            $table->unique(['price_list_id', 'product_id', 'nivel']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->text('descripcion')->nullable()->after('detalle');
            $table->unsignedSmallInteger('tamano_cm')->nullable()->after('unidades_por_caja');
            $table->json('etiquetas')->nullable();
            $table->boolean('visible_web')->default(true);
            $table->boolean('se_puede_pedir')->default(true);
            $table->json('dias_produccion')->nullable();
            $table->string('actualizado_por')->nullable();
        });

        // Los precios que ya estuvieran cargados quedan como Lista 1, igual para todos los niveles.
        $conPrecio = DB::table('products')->whereNotNull('precio')->get(['id', 'precio']);

        if ($conPrecio->isNotEmpty()) {
            $lista = DB::table('price_lists')->insertGetId([
                'numero' => 1,
                'vigente_desde' => now()->toDateString(),
                'publicada_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($conPrecio as $producto) {
                foreach (array_keys(config('tienda.niveles')) as $nivel) {
                    DB::table('prices')->insert([
                        'price_list_id' => $lista,
                        'product_id' => $producto->id,
                        'nivel' => $nivel,
                        'precio' => $producto->precio,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('precio');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('estado')->default('activo')->index();
            $table->string('nivel')->default('inicial');
            $table->string('metodo_pago')->nullable();
            $table->string('responsable')->nullable();
            $table->string('direccion')->nullable();
            $table->string('franja')->nullable();
            $table->string('ruta')->nullable();
            $table->string('nota_alta')->nullable();
            $table->text('nota_interna')->nullable();
            $table->timestamp('aprobado_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['estado']);
            $table->dropColumn(['estado', 'nivel', 'metodo_pago', 'responsable', 'direccion', 'franja', 'ruta', 'nota_alta', 'nota_interna', 'aprobado_at']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->decimal('precio', 12, 2)->nullable()->after('unidades_por_caja');
            $table->dropColumn(['descripcion', 'tamano_cm', 'etiquetas', 'visible_web', 'se_puede_pedir', 'dias_produccion', 'actualizado_por']);
        });

        Schema::dropIfExists('prices');
        Schema::dropIfExists('price_lists');
    }
};
