<?php

namespace App\Models;

use App\Enums\EstadoCliente;
use App\Enums\EstadoPedido;
use App\Enums\Franja;
use App\Enums\MetodoPago;
use App\Support\Formato;
use App\Support\Niveles;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

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
 * @property EstadoCliente $estado
 * @property string $nivel
 * @property MetodoPago|null $metodo_pago
 * @property string|null $responsable
 * @property string|null $direccion
 * @property Franja|null $franja
 * @property string|null $ruta
 * @property string|null $nota_alta
 * @property string|null $nota_interna
 * @property CarbonImmutable|null $aprobado_at
 * @property CarbonImmutable $created_at
 */
#[Fillable([
    'user_id', 'razon_social', 'cuit', 'tipo_comercio', 'localidad', 'whatsapp', 'email', 'condicion_iva', 'cuenta_corriente_dias',
    'estado', 'nivel', 'metodo_pago', 'responsable', 'direccion', 'franja', 'ruta', 'nota_alta', 'nota_interna', 'aprobado_at', 'created_at',
])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    protected $attributes = [
        'estado' => 'activo',
        'nivel' => 'inicial',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoCliente::class,
            'metodo_pago' => MetodoPago::class,
            'franja' => Franja::class,
            'aprobado_at' => 'datetime',
        ];
    }

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
        return $this->hasMany(Order::class)->chaperone();
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

    /**
     * "PD" para "Pizzería Don Carlos".
     */
    public function iniciales(): string
    {
        $iniciales = collect(preg_split('/\s+/', $this->razon_social, flags: PREG_SPLIT_NO_EMPTY) ?: [])
            ->filter(fn (string $palabra) => preg_match('/^\p{Lu}/u', $palabra) === 1)
            ->take(2)
            ->map(fn (string $palabra) => mb_substr($palabra, 0, 1))
            ->implode('');

        return $iniciales !== '' ? $iniciales : mb_strtoupper(mb_substr($this->razon_social, 0, 2));
    }

    public function nivelNombre(): string
    {
        return Niveles::nombre($this->nivel);
    }

    /**
     * "Transferencia" o "Cta. cte. 30 d".
     */
    public function formaDePago(): string
    {
        return $this->tieneCuentaCorriente()
            ? 'Cta. cte. '.$this->cuenta_corriente_dias.' d'
            : ($this->metodo_pago ?? MetodoPago::Transferencia)->etiqueta();
    }

    /**
     * "Tarde, 14 a 18 h. Ruta oeste."
     */
    public function entregas(): ?string
    {
        $partes = array_filter([
            $this->franja ? $this->franja->etiqueta().', '.$this->franja->horario() : null,
            $this->ruta ? 'Ruta '.$this->ruta : null,
        ]);

        return $partes === [] ? null : implode('. ', $partes).'.';
    }

    /**
     * Pedidos que cuentan como compras (todos menos los rechazados), del más nuevo al más viejo.
     * Usa la relación ya cargada si la hay.
     *
     * @return Collection<int, Order>
     */
    public function compras(): Collection
    {
        return $this->orders
            ->reject(fn (Order $order) => $order->estado === EstadoPedido::Rechazado)
            ->sortByDesc('created_at')
            ->values();
    }

    public function ultimaCompra(): ?CarbonImmutable
    {
        return $this->compras()->first()?->created_at;
    }

    /**
     * Sin compras en las últimas 3 semanas.
     */
    public function sinComprar(): bool
    {
        $ultima = $this->ultimaCompra();

        return $this->estado === EstadoCliente::Activo && ($ultima === null || $ultima->lt(today()->subWeeks(3)));
    }

    /**
     * "Hoy", "Lun 5 oct" o "Hace 5 semanas".
     */
    public function ultimaCompraTexto(): string
    {
        $ultima = $this->ultimaCompra();

        return match (true) {
            $ultima === null => 'Sin compras',
            $ultima->isToday() => 'Hoy',
            $ultima->isYesterday() => 'Ayer',
            $ultima->lt(today()->subWeeks(3)) => 'Hace '.(int) $ultima->diffInWeeks(now()).' semanas',
            default => Formato::fecha($ultima),
        };
    }

    /**
     * Cada cuántos días compra, en promedio, o null si no hay suficientes pedidos.
     */
    public function diasEntreCompras(): ?int
    {
        $fechas = $this->compras()->take(8)->map(fn (Order $order) => $order->created_at->startOfDay());

        if ($fechas->count() < 2) {
            return null;
        }

        return max(1, (int) round($fechas->first()->diffInDays($fechas->last()) / ($fechas->count() - 1)));
    }

    /**
     * Día de la semana en que pide siempre ("martes"), o null si varía.
     */
    public function diaHabitual(): ?string
    {
        $dias = $this->compras()->take(6)->map(fn (Order $order) => $order->created_at->dayOfWeekIso);

        if ($dias->count() < 3) {
            return null;
        }

        [$dia, $veces] = [$dias->mode()[0], $dias->countBy()->max()];

        return $veces / $dias->count() >= 0.6 ? Formato::fecha(today()->startOfWeek()->addDays($dia - 1), 'l') : null;
    }

    /**
     * "cada 7 días, martes" o "primer pedido".
     */
    public function frecuencia(): string
    {
        $dias = $this->diasEntreCompras();

        if ($dias === null) {
            return $this->compras()->isEmpty() ? 'todavía no compró' : 'primer pedido';
        }

        $dia = $this->diaHabitual();

        return 'cada '.$dias.' '.($dias === 1 ? 'día' : 'días').($dia ? ', '.mb_strtolower($dia) : '');
    }

    /**
     * @return Collection<int, Order>
     */
    public function pedidosPorCobrar(): Collection
    {
        return $this->compras()->reject(fn (Order $order) => $order->estaPagado())->values();
    }

    /**
     * Lo que debe, o null si alguno de los pedidos todavía no tiene total.
     */
    public function saldo(): ?float
    {
        $pedidos = $this->pedidosPorCobrar();

        return $pedidos->contains(fn (Order $order) => $order->total === null) ? null : (float) $pedidos->sum('total');
    }

    public function tieneVencido(): bool
    {
        return $this->pedidosPorCobrar()->contains(fn (Order $order) => $order->estaVencido());
    }

    public function cajasEntre(CarbonInterface $desde, CarbonInterface $hasta): int
    {
        return $this->compras()
            ->filter(fn (Order $order) => $order->created_at->between($desde, $hasta))
            ->sum(fn (Order $order) => $order->cajas());
    }

    public function aprobar(): void
    {
        $this->update(['estado' => EstadoCliente::Activo, 'aprobado_at' => now()]);
    }

    public function rechazar(): void
    {
        $this->update(['estado' => EstadoCliente::Rechazado]);
    }
}
