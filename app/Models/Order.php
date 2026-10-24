<?php

namespace App\Models;

use App\Enums\EstadoPedido;
use App\Enums\Franja;
use App\Enums\MetodoPago;
use App\Support\Formato;
use App\Support\Niveles;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $customer_id
 * @property EstadoPedido $estado
 * @property string $entrega_tipo
 * @property CarbonImmutable $entrega_fecha
 * @property Franja $franja
 * @property string|null $direccion
 * @property string|null $indicaciones
 * @property string|null $camion
 * @property MetodoPago $metodo_pago
 * @property string $nivel
 * @property string|null $subtotal
 * @property string|null $descuento
 * @property string|null $envio
 * @property string|null $iva
 * @property string|null $total
 * @property CarbonImmutable|null $aceptado_at
 * @property CarbonImmutable|null $entregado_at
 * @property CarbonImmutable|null $pagado_at
 * @property CarbonImmutable $created_at
 * @property-read Customer $customer
 */
#[Fillable([
    'customer_id', 'estado', 'entrega_tipo', 'entrega_fecha', 'franja', 'direccion', 'indicaciones', 'camion',
    'metodo_pago', 'nivel', 'subtotal', 'descuento', 'envio', 'iva', 'total',
    'aceptado_at', 'horno_at', 'listo_at', 'reparto_at', 'entregado_at', 'finalizado_at', 'rechazado_at', 'pagado_at',
    'created_at',
])]
class Order extends Model
{
    protected function casts(): array
    {
        return [
            'estado' => EstadoPedido::class,
            'franja' => Franja::class,
            'metodo_pago' => MetodoPago::class,
            'entrega_fecha' => 'date',
            'subtotal' => 'decimal:2',
            'descuento' => 'decimal:2',
            'envio' => 'decimal:2',
            'iva' => 'decimal:2',
            'total' => 'decimal:2',
            'aceptado_at' => 'datetime',
            'horno_at' => 'datetime',
            'listo_at' => 'datetime',
            'reparto_at' => 'datetime',
            'entregado_at' => 'datetime',
            'finalizado_at' => 'datetime',
            'rechazado_at' => 'datetime',
            'pagado_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasMany<OrderEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class)->latest()->latest('id');
    }

    /**
     * Pedidos que todavía no se entregaron.
     *
     * @param  Builder<Order>  $query
     */
    public function scopeActivos(Builder $query): void
    {
        $query->whereNotIn('estado', [EstadoPedido::Entregado, EstadoPedido::Finalizado, EstadoPedido::Rechazado]);
    }

    /**
     * @param  Builder<Order>  $query
     */
    public function scopePorCobrar(Builder $query): void
    {
        $query->whereNull('pagado_at')->where('estado', '!=', EstadoPedido::Rechazado);
    }

    /**
     * Nombre del nivel de precio que corresponde a una cantidad de cajas,
     * o al nivel asignado al cliente si es más alto.
     */
    public static function nivelPara(int $cajas, ?Customer $cliente = null): string
    {
        return Niveles::nombre(Niveles::paraCajas($cajas, $cliente?->nivel));
    }

    /**
     * Totales de un pedido: subtotal a precio del primer nivel, descuento por el
     * nivel aplicado, envío e IVA aparte. Null si a alguna línea le falta precio.
     *
     * @param  iterable<array{precios: array<string, string>, cajas: int}>  $lineas  precios por clave de nivel
     * @return array{subtotal: float, descuento: float, envio: float, iva: float, total: float}|null
     */
    public static function calcularTotales(iterable $lineas, string $nivel, bool $reparto): ?array
    {
        $base = Niveles::claves()[0];
        $subtotal = 0.0;
        $conDescuento = 0.0;

        foreach ($lineas as $linea) {
            if (! isset($linea['precios'][$base], $linea['precios'][$nivel])) {
                return null;
            }

            $subtotal += (float) $linea['precios'][$base] * $linea['cajas'];
            $conDescuento += (float) $linea['precios'][$nivel] * $linea['cajas'];
        }

        $envio = $reparto ? (float) config('tienda.envio') : 0.0;
        $iva = round(($conDescuento + $envio) * config('tienda.iva'), 2);

        return [
            'subtotal' => round($subtotal, 2),
            'descuento' => round($subtotal - $conDescuento, 2),
            'envio' => $envio,
            'iva' => $iva,
            'total' => round($conDescuento + $envio + $iva, 2),
        ];
    }

    /**
     * Cajas que la cocina ya tiene comprometidas para un día, por producto.
     *
     * @return Collection<int, int> product_id => cajas
     */
    public static function cajasComprometidas(CarbonInterface $fecha, ?int $excepto = null): Collection
    {
        return OrderItem::query()
            ->whereHas('order', fn (Builder $query) => $query
                ->whereIn('estado', EstadoPedido::enCocina())
                ->whereDate('entrega_fecha', $fecha)
                ->when($excepto, fn (Builder $query) => $query->whereKeyNot($excepto)))
            ->selectRaw('product_id, sum(cajas) as cajas')
            ->groupBy('product_id')
            ->pluck('cajas', 'product_id')
            ->map(fn ($cajas) => (int) $cajas);
    }

    public function numero(): string
    {
        return '#'.$this->id;
    }

    public function cajas(): int
    {
        return (int) $this->items->sum('cajas');
    }

    public function esReparto(): bool
    {
        return $this->entrega_tipo === 'reparto';
    }

    public function estaPagado(): bool
    {
        return $this->pagado_at !== null;
    }

    public function puedeAvanzar(): bool
    {
        $siguiente = $this->estado->siguiente();

        return $siguiente !== null && ($siguiente !== EstadoPedido::Finalizado || $this->estaPagado());
    }

    /**
     * Lleva el pedido al estado siguiente y lo registra en la actividad.
     */
    public function avanzar(string $autor): void
    {
        if (! $this->puedeAvanzar()) {
            return;
        }

        $siguiente = $this->estado->siguiente();

        DB::transaction(function () use ($siguiente, $autor) {
            $this->estado = $siguiente;
            $this->{$siguiente->columnaFecha()} = now();
            $this->save();

            if ($siguiente === EstadoPedido::Listo) {
                $this->items()->update(['listo' => true]);
            }

            $this->registrar($autor, $siguiente->actividad());
        });
    }

    public function rechazar(string $autor): void
    {
        if ($this->estado !== EstadoPedido::Solicitud) {
            return;
        }

        $this->update(['estado' => EstadoPedido::Rechazado, 'rechazado_at' => now()]);
        $this->registrar($autor, EstadoPedido::Rechazado->actividad());
    }

    public function registrarPago(string $autor): void
    {
        if ($this->estaPagado()) {
            return;
        }

        $this->update(['pagado_at' => now()]);
        $this->registrar($autor, 'Registró el pago.');
    }

    public function registrar(string $autor, string $descripcion): void
    {
        $this->events()->create(['autor' => $autor, 'descripcion' => $descripcion]);
    }

    /**
     * Fecha en la que vence el pago, o null si todavía no corre.
     */
    public function vencimiento(): ?CarbonImmutable
    {
        if ($this->metodo_pago === MetodoPago::CuentaCorriente) {
            $dias = $this->customer->cuenta_corriente_dias ?? config('tienda.cuenta_corriente_dias');

            return ($this->entregado_at ?? $this->entrega_fecha)->startOfDay()->addDays($dias);
        }

        return $this->entregado_at;
    }

    public function estaVencido(): bool
    {
        $vencimiento = $this->vencimiento();

        return ! $this->estaPagado()
            && $vencimiento !== null
            && in_array($this->estado, [EstadoPedido::Entregado, EstadoPedido::Finalizado])
            && $vencimiento->isPast();
    }

    /**
     * Badge del estado de pago: [texto, clases].
     *
     * @return array{0: string, 1: string}
     */
    public function badgePago(): array
    {
        return match (true) {
            $this->estaPagado() => ['Pagado', 'bg-albahaca-claro text-albahaca'],
            $this->metodo_pago === MetodoPago::CuentaCorriente => ['Cta. cte. · '.($this->customer->cuenta_corriente_dias ?? config('tienda.cuenta_corriente_dias')).' d', 'bg-queso-claro text-carbon'],
            default => ['Por pagar', 'border border-tomate bg-white text-tomate'],
        };
    }

    /**
     * Badge de vencimiento para la tabla de cobranzas: [texto, clases].
     *
     * @return array{0: string, 1: string}
     */
    public function badgeVencimiento(): array
    {
        if ($this->estado === EstadoPedido::Solicitud) {
            return ['Pedido sin aprobar', 'bg-queso text-carbon'];
        }

        if ($this->estaVencido()) {
            $dias = max(1, (int) $this->vencimiento()->diffInDays(now()));

            return ['Vencido · '.$dias.' '.($dias === 1 ? 'día' : 'días'), 'border border-tomate bg-white text-tomate'];
        }

        $vencimiento = $this->vencimiento();

        if ($vencimiento === null) {
            return ['Vence al entregar', 'border border-linea bg-white text-carbon'];
        }

        return ['Vence '.mb_strtolower(Formato::fecha($vencimiento, 'j M')), 'bg-queso-claro text-carbon'];
    }

    /**
     * "Hoy", "Mañana" o "Mié 7 oct".
     */
    public function entregaRelativa(): string
    {
        return match (true) {
            $this->entrega_fecha->isToday() => 'Hoy',
            $this->entrega_fecha->isTomorrow() => 'Mañana',
            default => Formato::fecha($this->entrega_fecha),
        };
    }

    /**
     * "Hoy 13:58" o "Lun 5 · 19:20".
     */
    public function solicitadoRelativo(): string
    {
        return $this->created_at->isToday()
            ? 'Hoy '.$this->created_at->format('H:i')
            : Formato::fecha($this->created_at, 'D j').' · '.$this->created_at->format('H:i');
    }
}
