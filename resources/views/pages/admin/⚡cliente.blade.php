<?php

use App\Enums\EstadoCliente;
use App\Enums\Franja;
use App\Enums\MetodoPago;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\Formato;
use App\Support\Niveles;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public Customer $customer;

    public bool $editando = false;

    /** @var array<string, string|null> */
    public array $datos = [];

    public string $nivel = '';

    public string $pago = '';

    public function mount(Customer $customer): void
    {
        $this->nivel = $customer->nivel;
        $this->pago = ($customer->metodo_pago ?? MetodoPago::Transferencia)->value;
    }

    #[Computed]
    public function compras(): Collection
    {
        return $this->customer->load('orders.items.product')->compras();
    }

    /**
     * Movimientos de la cuenta, del más nuevo al más viejo, con el saldo acumulado.
     *
     * @return Collection<int, array{fecha: Carbon\CarbonInterface, movimiento: string, debe: string|null, haber: string|null, saldo: float|null, order: Order}>
     */
    #[Computed]
    public function movimientos(): Collection
    {
        $movimientos = collect();

        foreach ($this->compras as $order) {
            $movimientos->push(['fecha' => $order->created_at, 'movimiento' => 'Pedido '.$order->numero(), 'debe' => $order->total, 'haber' => null, 'order' => $order]);

            if ($order->estaPagado()) {
                $movimientos->push([
                    'fecha' => $order->pagado_at,
                    'movimiento' => $order->metodo_pago === MetodoPago::Transferencia ? 'Transferencia recibida' : 'Pago recibido · '.$order->metodo_pago->etiqueta(),
                    'debe' => null,
                    'haber' => $order->total,
                    'order' => $order,
                ]);
            }
        }

        $saldo = 0.0;

        return $movimientos
            ->sortBy(fn (array $movimiento) => $movimiento['fecha']->getTimestamp())
            ->values()
            ->map(function (array $movimiento) use (&$saldo) {
                $importe = $movimiento['debe'] ?? $movimiento['haber'];
                $saldo = $saldo === null || $importe === null ? null : $saldo + ($movimiento['debe'] !== null ? 1 : -1) * (float) $importe;

                return [...$movimiento, 'saldo' => $saldo];
            })
            ->reverse()
            ->values();
    }

    /**
     * @return Collection<int, array{product: App\Models\Product, cajas: int}>
     */
    #[Computed]
    public function masPedidos(): Collection
    {
        return OrderItem::with('product')
            ->whereIn('order_id', $this->compras->filter(fn (Order $order) => $order->created_at->gte(today()->subDays(30)))->pluck('id'))
            ->get()
            ->groupBy('product_id')
            ->map(fn (Collection $items) => ['product' => $items->first()->product, 'cajas' => (int) $items->sum('cajas')])
            ->sortByDesc('cajas')
            ->take(3)
            ->values();
    }

    public function editar(): void
    {
        $this->datos = $this->customer->only(['razon_social', 'tipo_comercio', 'localidad', 'responsable', 'whatsapp', 'email', 'direccion', 'ruta', 'nota_interna']);
        $this->datos['franja'] = $this->customer->franja?->value;
        $this->editando = true;
    }

    public function guardarDatos(): void
    {
        $this->validate([
            'datos.razon_social' => ['required', 'string', 'max:255'],
            'datos.tipo_comercio' => ['nullable', 'string', 'max:100'],
            'datos.localidad' => ['nullable', 'string', 'max:100'],
            'datos.responsable' => ['nullable', 'string', 'max:255'],
            'datos.whatsapp' => ['nullable', 'string', 'max:50'],
            'datos.email' => ['nullable', 'email', 'max:255'],
            'datos.direccion' => ['nullable', 'string', 'max:255'],
            'datos.franja' => ['nullable', Rule::enum(Franja::class)],
            'datos.ruta' => ['nullable', 'string', 'max:100'],
            'datos.nota_interna' => ['nullable', 'string', 'max:1000'],
        ], attributes: ['datos.razon_social' => 'razón social']);

        $this->customer->update(collect($this->datos)->map(fn ($valor) => filled($valor) ? $valor : null)->all());
        $this->editando = false;
    }

    public function updatedNivel(): void
    {
        $this->validateOnly('nivel', ['nivel' => ['required', Rule::in(Niveles::claves())]]);
        $this->customer->update(['nivel' => $this->nivel]);
    }

    public function updatedPago(): void
    {
        $this->validateOnly('pago', ['pago' => ['required', Rule::enum(MetodoPago::class)]]);
        $this->customer->update(['metodo_pago' => $this->pago]);
    }

    public function alternarCuentaCorriente(): void
    {
        $this->customer->update(['cuenta_corriente_dias' => $this->customer->tieneCuentaCorriente() ? null : 30]);
    }

    public function aprobar(): void
    {
        if ($this->customer->estado === EstadoCliente::Pendiente) {
            $this->customer->aprobar();
        }
    }

    public function rechazar(): void
    {
        if ($this->customer->estado === EstadoCliente::Pendiente) {
            $this->customer->rechazar();
        }
    }

    public function render()
    {
        return $this->view()
            ->layout('layouts::admin', ['migas' => ['Clientes', $this->customer->razon_social]])
            ->title($this->customer->razon_social);
    }
}; ?>

@php
    $compras = $this->compras;
    $hoy = now();
    $cajasMes = $customer->cajasEntre($hoy->copy()->startOfMonth(), $hoy);
    $cajasMesPasado = $customer->cajasEntre($hoy->copy()->subMonthNoOverflow()->startOfMonth(), $hoy->copy()->subMonthNoOverflow()->endOfMonth());
    $diferencia = $cajasMes - $cajasMesPasado;

    $siguiente = Niveles::siguiente($customer->nivel);
    $promedio = $compras->take(5)->avg(fn ($order) => $order->cajas()) ?? 0;
    $porCobrar = $customer->pedidosPorCobrar()->sortBy('created_at');
    $franjaHabitual = $compras->take(6)->countBy(fn ($order) => $order->franja->value)->sortDesc()->keys()->first();
@endphp

<div>
    <a href="{{ route('admin.clientes') }}" wire:navigate class="text-sm font-semibold text-tomate underline underline-offset-4">← Volver a clientes</a>

    <div class="mb-6 mt-3 flex flex-wrap items-end justify-between gap-4">
        <div class="flex items-center gap-4">
            <span class="flex size-16 shrink-0 items-center justify-center rounded-full bg-tomate text-xl font-bold text-white">{{ $customer->iniciales() }}</span>
            <div>
                <h1 class="font-display text-4xl uppercase leading-none sm:text-5xl">{{ $customer->razon_social }}</h1>
                <p class="mt-2 text-sm text-tinta">
                    {{ collect([$customer->tipo_comercio, $customer->localidad])->filter()->implode(' en ') }}{{ $customer->tipo_comercio || $customer->localidad ? '.' : '' }}
                    CUIT {{ $customer->cuitFormateado() }}. Cliente desde {{ mb_strtolower(Formato::fecha($customer->aprobado_at ?? $customer->created_at, 'F \d\e Y')) }}.
                </p>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <x-admin.boton-pronto>Enviar estado de cuenta</x-admin.boton-pronto>
            <button type="button" wire:click="editar" class="tienda-btn-borde !py-2.5">Editar datos</button>
            <x-admin.boton-pronto class="!border-tomate !bg-tomate !text-white">Cargar pedido</x-admin.boton-pronto>
        </div>
    </div>

    @if ($customer->estado !== EstadoCliente::Activo)
        <div @class(['mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl px-5 py-3.5 text-sm', 'bg-queso-claro' => $customer->estado === EstadoCliente::Pendiente, 'bg-arena' => $customer->estado === EstadoCliente::Rechazado])>
            <p>
                <span class="font-bold">{{ $customer->estado->etiqueta() }}.</span>
                {{ $customer->estado === EstadoCliente::Pendiente ? ($customer->nota_alta ?? 'Falta revisar CUIT y zona de reparto.') : 'El alta de este comercio fue rechazada.' }}
            </p>
            @if ($customer->estado === EstadoCliente::Pendiente)
                <div class="flex gap-2">
                    <button type="button" wire:click="rechazar" wire:confirm="¿Rechazar el alta de {{ $customer->razon_social }}?" class="tienda-btn-borde !px-3 !py-2">Rechazar</button>
                    <button type="button" wire:click="aprobar" class="tienda-btn-primario !px-3 !py-2">Aprobar alta</button>
                </div>
            @endif
        </div>
    @endif

    <div class="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.kpi
            titulo="Nivel"
            :valor="$customer->nivelNombre()"
            :detalle="$siguiente
                ? 'le faltan '.max(1, Niveles::todos()[$siguiente]['desde'] - (int) round($promedio)).' cajas por pedido para '.Niveles::nombre($siguiente)
                : 'el nivel más alto'"
        />
        <x-admin.kpi
            titulo="Cajas este mes"
            :valor="$cajasMes"
            :detalle="match (true) { $diferencia > 0 => $diferencia.' más que el mes pasado', $diferencia < 0 => abs($diferencia).' menos que el mes pasado', default => 'igual que el mes pasado' }"
        />
        <x-admin.kpi
            titulo="Suele pedir"
            :valor="$customer->diaHabitual() ?? '—'"
            :detalle="$customer->diasEntreCompras()
                ? 'cada '.$customer->diasEntreCompras().' días'.($franjaHabitual ? ', a la '.mb_strtolower(Franja::from($franjaHabitual)->etiqueta()) : '')
                : $customer->frecuencia()"
        />
        <x-admin.kpi
            titulo="Saldo a pagar"
            :valor="$porCobrar->isEmpty() ? '$ 0' : Formato::dinero($customer->saldo())"
            :detalle="$porCobrar->isEmpty() ? 'al día' : 'pedido '.$porCobrar->first()->numero().', '.mb_strtolower($porCobrar->first()->badgeVencimiento()[0]).($porCobrar->count() > 1 ? ' y '.($porCobrar->count() - 1).' más' : '')"
            :destacado="$porCobrar->isNotEmpty()"
        />
    </div>

    <div class="grid gap-4 lg:grid-cols-[17rem_1fr] xl:grid-cols-[17rem_1fr_15.5rem]">
        <div class="space-y-4">
            <x-admin.tarjeta titulo="Contacto">
                @if ($editando)
                    <form wire:submit="guardarDatos" class="space-y-3 text-sm">
                        @foreach ([
                            'razon_social' => 'Razón social',
                            'tipo_comercio' => 'Rubro',
                            'localidad' => 'Localidad',
                            'responsable' => 'Responsable',
                            'whatsapp' => 'WhatsApp',
                            'email' => 'Email',
                            'direccion' => 'Dirección',
                            'ruta' => 'Ruta de reparto',
                        ] as $campo => $texto)
                            <label class="block">
                                <span class="tienda-label">{{ $texto }}</span>
                                <input type="text" wire:model="datos.{{ $campo }}" class="tienda-input !py-2">
                                @error('datos.'.$campo) <span class="mt-1 block text-xs text-tomate">{{ $message }}</span> @enderror
                            </label>
                        @endforeach
                        <label class="block">
                            <span class="tienda-label">Franja de entrega</span>
                            <select wire:model="datos.franja" class="tienda-input !py-2">
                                <option value="">Sin preferencia</option>
                                @foreach (Franja::cases() as $franja)
                                    <option value="{{ $franja->value }}">{{ $franja->etiqueta() }}, {{ $franja->horario() }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block">
                            <span class="tienda-label">Nota interna</span>
                            <textarea wire:model="datos.nota_interna" rows="3" class="tienda-input"></textarea>
                        </label>
                        <div class="flex gap-2">
                            <button type="button" wire:click="$set('editando', false)" class="tienda-btn-borde flex-1 !py-2">Cancelar</button>
                            <button type="submit" class="tienda-btn-oscuro flex-1 !py-2">Guardar</button>
                        </div>
                    </form>
                @else
                    <dl class="grid grid-cols-[6rem_1fr] gap-x-3 gap-y-2.5 text-sm">
                        @foreach ([
                            'Responsable' => $customer->responsable,
                            'WhatsApp' => $customer->whatsapp,
                            'Email' => $customer->email,
                            'Dirección' => collect([$customer->direccion, $customer->localidad])->filter()->implode(', '),
                            'Entregas' => $customer->entregas(),
                        ] as $texto => $valor)
                            <dt class="text-tinta">{{ $texto }}</dt>
                            <dd class="break-words">
                                @if ($texto === 'WhatsApp' && $valor)
                                    <a href="https://wa.me/{{ preg_replace('/\D/', '', $valor) }}" target="_blank" rel="noopener" class="hover:underline">{{ $valor }}</a>
                                @elseif ($texto === 'Email' && $valor)
                                    <a href="mailto:{{ $valor }}" class="hover:underline">{{ $valor }}</a>
                                @else
                                    {{ $valor ?: '—' }}
                                @endif
                            </dd>
                        @endforeach
                    </dl>
                @endif
            </x-admin.tarjeta>

            <x-admin.tarjeta titulo="Condiciones">
                <div class="space-y-4 text-sm">
                    <label class="block">
                        <span class="tienda-label">Nivel de precio</span>
                        <select wire:model.live="nivel" class="tienda-input">
                            @foreach (Niveles::claves() as $clave)
                                <option value="{{ $clave }}">{{ Niveles::nombre($clave) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block">
                        <span class="tienda-label">Forma de pago</span>
                        <select wire:model.live="pago" class="tienda-input">
                            @foreach (MetodoPago::cases() as $metodo)
                                @continue($metodo === MetodoPago::CuentaCorriente)
                                <option value="{{ $metodo->value }}">{{ $metodo->etiqueta() }}{{ $metodo === MetodoPago::Transferencia ? ', contra entrega' : '' }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div class="flex items-center justify-between gap-3 border-b border-linea pb-3">
                        <div>
                            <p class="font-bold">Cuenta corriente</p>
                            <p class="text-xs text-tinta">{{ $customer->tieneCuentaCorriente() ? 'Habilitada a '.$customer->cuenta_corriente_dias.' días' : 'Hoy no la tiene habilitada' }}</p>
                        </div>
                        <x-admin.interruptor :activo="$customer->tieneCuentaCorriente()" etiqueta="Cuenta corriente" wire:click="alternarCuentaCorriente" />
                    </div>
                </div>
            </x-admin.tarjeta>

            @if ($customer->nota_interna && ! $editando)
                <div class="rounded-2xl border border-queso bg-queso-claro p-5 text-sm">
                    <p class="font-bold">Nota interna</p>
                    <p class="mt-1 whitespace-pre-line">{{ $customer->nota_interna }}</p>
                </div>
            @endif
        </div>

        <div class="min-w-0 space-y-4">
            <section class="overflow-hidden rounded-2xl border border-linea bg-white">
                <div class="flex items-center justify-between gap-3 px-5 pt-5">
                    <h2 class="font-display text-2xl uppercase">Últimos pedidos</h2>
                    @if ($this->compras->isNotEmpty())
                        <a href="{{ route('admin.pedidos', ['q' => $customer->cuit]) }}" wire:navigate class="text-sm font-semibold text-tomate underline underline-offset-4">Ver los {{ $this->compras->count() }} pedidos</a>
                    @endif
                </div>
                <div class="overflow-x-auto">
                    <table class="mt-3 w-full min-w-[34rem] text-sm">
                        <thead class="border-b-2 border-carbon text-left text-[0.65rem] font-bold uppercase tracking-[0.12em] text-tinta">
                            <tr>
                                <th class="px-5 py-2.5">Pedido</th>
                                <th class="px-2 py-2.5">Entrega</th>
                                <th class="px-2 py-2.5 text-center">Cajas</th>
                                <th class="px-2 py-2.5">Estado</th>
                                <th class="px-2 py-2.5">Pago</th>
                                <th class="px-5 py-2.5 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-linea">
                            @forelse ($this->compras->take(4) as $order)
                                <tr wire:key="pedido-{{ $order->id }}">
                                    <td class="px-5 py-3"><a href="{{ route('admin.pedido', $order) }}" wire:navigate class="font-bold hover:underline">{{ $order->numero() }}</a></td>
                                    <td class="px-2 py-3">{{ $order->entregaRelativa() }}</td>
                                    <td class="px-2 py-3 text-center font-bold">{{ $order->cajas() }}</td>
                                    <td class="px-2 py-3"><x-admin.estado :estado="$order->estado" /></td>
                                    <td class="px-2 py-3"><x-admin.pago :order="$order" /></td>
                                    <td class="px-5 py-3 text-right">{{ Formato::dinero($order->total) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-5 py-6 text-center text-tinta">Todavía no hizo pedidos.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-linea bg-white">
                <div class="flex items-center justify-between gap-3 px-5 pt-5">
                    <h2 class="font-display text-2xl uppercase">Estado de cuenta</h2>
                    <span class="cursor-not-allowed text-sm font-semibold text-tomate underline underline-offset-4 opacity-60" title="Próximamente">Descargar PDF</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="mt-3 w-full min-w-[34rem] text-sm">
                        <thead class="border-b-2 border-carbon text-left text-[0.65rem] font-bold uppercase tracking-[0.12em] text-tinta">
                            <tr>
                                <th class="px-5 py-2.5">Fecha</th>
                                <th class="px-2 py-2.5">Movimiento</th>
                                <th class="px-2 py-2.5 text-right">Debe</th>
                                <th class="px-2 py-2.5 text-right">Haber</th>
                                <th class="px-5 py-2.5 text-right">Saldo</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-linea">
                            @forelse ($this->movimientos->take(5) as $i => $movimiento)
                                <tr wire:key="movimiento-{{ $i }}">
                                    <td class="px-5 py-2.5">{{ $movimiento['fecha']->isToday() ? 'Hoy' : Formato::fecha($movimiento['fecha']) }}</td>
                                    <td class="px-2 py-2.5">{{ $movimiento['movimiento'] }}</td>
                                    <td class="px-2 py-2.5 text-right">{{ $movimiento['haber'] === null ? Formato::dinero($movimiento['debe']) : '' }}</td>
                                    <td class="px-2 py-2.5 text-right text-albahaca">{{ $movimiento['debe'] === null ? Formato::dinero($movimiento['haber']) : '' }}</td>
                                    <td class="px-5 py-2.5 text-right font-bold">{{ Formato::dinero($movimiento['saldo']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-5 py-6 text-center text-tinta">Sin movimientos.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <x-admin.tarjeta titulo="Lo que más pide" class="self-start lg:col-start-2 xl:col-start-auto">
            <p class="-mt-2 mb-3 text-xs text-tinta">Cajas en los últimos 30 días</p>
            @php $maximo = max(1, $this->masPedidos->max('cajas')); @endphp
            <ul class="space-y-3">
                @forelse ($this->masPedidos as $fila)
                    <li class="flex items-center gap-3">
                        <x-tienda.miniatura :producto="$fila['product']" class="size-7 shrink-0" />
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold leading-tight">{{ $fila['product']->nombre }}</p>
                            <div class="mt-1.5 h-1.5 rounded-full bg-arena">
                                <div class="h-full rounded-full bg-tomate" style="width: {{ $fila['cajas'] / $maximo * 100 }}%"></div>
                            </div>
                        </div>
                        <span class="w-9 text-right font-display text-2xl">{{ $fila['cajas'] }}</span>
                    </li>
                @empty
                    <li class="text-sm text-tinta">Sin pedidos en los últimos 30 días.</li>
                @endforelse
            </ul>
            <x-admin.boton-pronto class="mt-4 w-full">Repetir último pedido</x-admin.boton-pronto>
        </x-admin.tarjeta>
    </div>
</div>
