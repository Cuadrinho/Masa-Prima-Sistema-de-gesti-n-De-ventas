<?php

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $razon_social
 * @property string $cuit
 * @property string|null $tipo_comercio
 * @property string|null $localidad
 * @property string|null $whatsapp
 * @property string|null $email
 * @property string $condicion_iva
 * @property int|null $cuenta_corriente_dias
 */
#[Fillable(['user_id', 'razon_social', 'cuit', 'tipo_comercio', 'localidad', 'whatsapp', 'email', 'condicion_iva', 'cuenta_corriente_dias'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * "Pizzería · Laferrere"
     */
    public function descripcion(): string
    {
        return collect([$this->tipo_comercio, $this->localidad])->filter()->implode(' · ');
    }

    public function cuitFormateado(): string
    {
        if (strlen($this->cuit) !== 11) {
            return $this->cuit;
        }

        return substr($this->cuit, 0, 2).'-'.substr($this->cuit, 2, 8).'-'.substr($this->cuit, 10);
    }

    public function comprobante(): string
    {
        return $this->condicion_iva === 'A'
            ? 'Factura A — Responsable Inscripto'
            : 'Factura B — Monotributo / Exento';
    }

    public function tieneCuentaCorriente(): bool
    {
        return $this->cuenta_corriente_dias !== null;
    }
}
