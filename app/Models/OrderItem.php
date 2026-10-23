<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
 * @property int $product_id
 * @property int $cajas
 * @property string|null $precio_unitario
 * @property bool $listo
 * @property-read Product $product
 */
#[Fillable(['product_id', 'cajas', 'precio_unitario', 'listo'])]
class OrderItem extends Model
{
    protected function casts(): array
    {
        return [
            'precio_unitario' => 'decimal:2',
            'listo' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function importe(): ?float
    {
        return $this->precio_unitario === null ? null : (float) $this->precio_unitario * $this->cajas;
    }
}
