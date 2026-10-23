<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('nombre');
            $table->string('nombre_corto');
            $table->string('categoria')->index();
            $table->string('detalle')->nullable();
            $table->string('ilustracion')->default('prepizza');
            $table->unsignedSmallInteger('unidades_por_caja')->nullable();
            $table->decimal('precio', 12, 2)->nullable();
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('razon_social');
            $table->string('cuit', 11)->unique();
            $table->string('tipo_comercio')->nullable();
            $table->string('localidad')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('email')->nullable();
            $table->char('condicion_iva', 1)->default('A');
            $table->unsignedSmallInteger('cuenta_corriente_dias')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('estado')->index();
            $table->string('entrega_tipo');
            $table->date('entrega_fecha')->index();
            $table->string('franja');
            $table->string('direccion')->nullable();
            $table->text('indicaciones')->nullable();
            $table->string('camion')->nullable();
            $table->string('metodo_pago');
            $table->string('nivel');
            $table->decimal('subtotal', 12, 2)->nullable();
            $table->decimal('descuento', 12, 2)->nullable();
            $table->decimal('envio', 12, 2)->nullable();
            $table->decimal('iva', 12, 2)->nullable();
            $table->decimal('total', 12, 2)->nullable();
            $table->timestamp('aceptado_at')->nullable();
            $table->timestamp('horno_at')->nullable();
            $table->timestamp('listo_at')->nullable();
            $table->timestamp('reparto_at')->nullable();
            $table->timestamp('entregado_at')->nullable();
            $table->timestamp('finalizado_at')->nullable();
            $table->timestamp('rechazado_at')->nullable();
            $table->timestamp('pagado_at')->nullable();
            $table->timestamps();
        });

        // Los números de pedido arrancan en 1001.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER SEQUENCE orders_id_seq RESTART WITH 1001');
        }

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('cajas');
            $table->decimal('precio_unitario', 12, 2)->nullable();
            $table->boolean('listo')->default(false);
            $table->timestamps();
        });

        Schema::create('order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('autor');
            $table->string('descripcion');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_events');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('products');
    }
};
