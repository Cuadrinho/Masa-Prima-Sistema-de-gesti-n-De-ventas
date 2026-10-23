<?php

use App\Enums\EstadoPedido;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\Formato;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::admin', ['migas' => ['Panel general']])] #[Title('Panel general')] class extends Component {
    #[Computed]
    public function solicitudes(): Collection
    {
        return Order::with(['customer', 'items.product'])
            ->where('estado', EstadoPedido::Solicitud)
            ->latest()
            ->get();
    }

    /**
     * Cajas de las comandas aceptadas que entregan hoy (o atrasadas), por variedad.
     */
    #[Computed]
    public function aProducir(): Collection
    {
        return OrderItem::with('product')
            ->whereHas('order', fn ($query) => $query
                ->whereIn('estado', [EstadoPedido::PorPreparar, EstadoPedido::EnHorno])
                ->whereDate('entrega_fecha', '<=', today()))
            ->get()
            ->groupBy('product_id')
            ->map(fn (Collection $items) => ['product' => $items->first()->product, 'cajas' => $items->sum('cajas')])
            ->sortBy(fn (array $fila) => $fila['product']->orden)
            ->values();
    }

    #[Computed]
    public function comandasEnCocina(): int
    {
        return Order::whereIn('estado', [EstadoPedido::PorPreparar, EstadoPedido::EnHorno])
            ->whereDate('entrega_fecha', '<=', today())
            ->count();
    }

    #[Computed]
    public function entregasDeHoy(): Collection
    {
        return Order::with(['customer', 'items'])
            ->whereDate('entrega_fecha', today())
            ->whereNotIn('estado', [EstadoPedido::Solicitud, EstadoPedido::Rechazado, EstadoPedido::Finalizado])
            ->orderBy('franja')
            ->orderBy('id')
            ->get();
    }

    #[Computed]
    public function porCobrar(): Collection
    {
        return Order::with('customer')->porCobrar()->get();
    }

    /**
     * Pedidos y cajas por estado, para la línea de producción.
     *
     * @return array<string, array{pedidos: int, cajas: int}>
     */
    #[Computed]
    public function circuito(): array
    {
        $pedidos = Order::with('items')
            ->where(fn ($query) => $query
                ->where('estado', '!=', EstadoPedido::Finalizado)
                ->orWhere('finalizado_at', '>=', now()->subDays(6)->startOfDay()))
            ->get()
            ->groupBy(fn (Order $order) => $order->estado->value);

        return collect(EstadoPedido::circuito())->mapWithKeys(fn (EstadoPedido $estado) => [$estado->value => [
            'pedidos' => $pedidos->get($estado->value, collect())->count(),
            'cajas' => $pedidos->get($estado->value, collect())->sum(fn (Order $order) => $order->cajas()),
        ]])->all();
    }

    public function aceptar(int $id): void
    {
        Order::findOrFail($id)->avanzar(auth()->user()->firma());
    }

    public function rechazar(int $id): void
    {
        Order::findOrFail($id)->rechazar(auth()->user()->firma());
    }
}; ?>

<div wire:poll.30s>
    <x-admin.encabezado antetitulo="Panel administrativo · Venta mayorista" titulo="Resumen del día">
        <x-slot:acciones>
            <x-admin.boton-pronto>Exportar</x-admin.boton-pronto>
            <x-admin.boton-pronto oscuro>+ Cargar pedido manual</x-admin.boton-pronto>
        </x-slot:acciones>
    </x-admin.encabezado>

    {{-- Indicadores --}}
    @php
        $activos = Order::activos()->count();
        $enReparto = $this->circuito[EstadoPedido::EnReparto->value]['pedidos'];
        $vencidos = $this->porCobrar->filter->estaVencido();
        $montoPorCobrar = $this->porCobrar->contains(fn ($order) => $order->total === null) ? null : $this->porCobrar->sum('total');
    @endphp
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <x-admin.kpi titulo="Pedidos activos" :valor="$activos" :detalle="$this->solicitudes->count().' solicitudes nuevas para aprobar'" />
        <x-admin.kpi titulo="Cajas a producir hoy" :valor="$this->aProducir->sum('cajas')" :detalle="$this->comandasEnCocina.' comandas en cocina'" destacado />
        <x-admin.kpi titulo="Por entregar hoy" :valor="$this->entregasDeHoy->where('estado', '!=', EstadoPedido::Entregado)->count()" :detalle="$enReparto.' '.($enReparto === 1 ? 'camión' : 'camiones').' en reparto'" />
        <x-admin.kpi titulo="Por cobrar" :valor="Formato::dinero($montoPorCobrar, 'MONTO')" :detalle="$this->porCobrar->count().' pedidos · '.$vencidos->count().' '.($vencidos->count() === 1 ? 'vencido' : 'vencidos')" />
        <x-admin.kpi titulo="Finalizados · semana" :valor="$this->circuito[EstadoPedido::Finalizado->value]['pedidos']" detalle="entregados y cobrados" />
    </div>

    {{-- Línea de producción --}}
    <div class="mt-4 flex gap-2 overflow-x-auto rounded-2xl border border-linea bg-white p-3">
        @foreach (EstadoPedido::circuito() as $estado)
            @if (! $loop->first)
                <span class="self-center text-tinta/50">→</span>
            @endif
            <div class="flex min-w-36 flex-1 items-center gap-3 rounded-lg px-3 py-2.5 {{ $estado->clasesTarjeta() }}">
                <span class="font-display text-3xl">{{ str_pad($this->circuito[$estado->value]['pedidos'], 2, '0', STR_PAD_LEFT) }}</span>
                <span class="leading-tight">
                    <span class="block text-sm font-bold">{{ $estado->etiquetaCorta() }}</span>
                    <span class="block text-xs opacity-75">{{ $this->circuito[$estado->value]['cajas'] }} cajas</span>
                </span>
            </div>
        @endforeach
    </div>

    <div class="mt-4 grid gap-4 xl:grid-cols-[1fr_0.85fr_1.25fr]">
        {{-- Nuevas solicitudes --}}
        <x-admin.tarjeta titulo="Nuevas solicitudes" :enlace="route('admin.pedidos', ['estado' => 'solicitud'])" texto-enlace="Ver todas">
            <div class="space-y-3">
                @forelse ($this->solicitudes as $order)
                    <article wire:key="solicitud-{{ $order->id }}" class="rounded-xl border border-linea p-4">
                        <div class="flex items-center gap-2">
                            <a href="{{ route('admin.pedido', $order) }}" wire:navigate class="font-bold hover:underline">{{ $order->numero() }}</a>
                            <x-admin.estado :estado="$order->estado" />
                            <span class="ml-auto text-xs text-tinta">{{ $order->solicitadoRelativo() }}</span>
                        </div>
                        <h3 class="mt-2 font-bold">{{ $order->customer->razon_social }}</h3>
                        <p class="text-xs text-tinta">{{ $order->customer->descripcion() }} · Entrega {{ Formato::fecha($order->entrega_fecha) }}, {{ mb_strtolower($order->franja->etiqueta()) }}</p>
                        <ul class="mt-3 space-y-1.5 text-sm">
                            @foreach ($order->items as $item)
                                <li class="flex items-center gap-2">
                                    <x-tienda.miniatura :producto="$item->product" class="size-5" />
                                    <span class="flex-1">{{ $item->product->nombre }}</span>
                                    <span class="font-semibold">{{ $item->cajas }} cajas</span>
                                </li>
                            @endforeach
                        </ul>
                        <div class="mt-3 flex items-center justify-between border-t border-dashed border-linea pt-3">
                            <span class="text-xs text-tinta">{{ $order->cajas() }} cajas · {{ $order->metodo_pago->etiqueta() }}</span>
                            <span class="font-bold">{{ Formato::dinero($order->total) }}</span>
                        </div>
                        <div class="mt-3 grid grid-cols-[1fr_1.4fr] gap-2">
                            <button type="button" wire:click="rechazar({{ $order->id }})" wire:confirm="¿Rechazar el pedido {{ $order->numero() }}?" class="tienda-btn-borde !py-2">Rechazar</button>
                            <button type="button" wire:click="aceptar({{ $order->id }})" class="tienda-btn-primario !py-2">Aceptar → cocina</button>
                        </div>
                    </article>
                @empty
                    <p class="rounded-xl bg-harina p-6 text-center text-sm text-tinta">No hay solicitudes nuevas.</p>
                @endforelse
            </div>
        </x-admin.tarjeta>

        {{-- A producir hoy --}}
        <x-admin.tarjeta titulo="A producir hoy" :enlace="route('admin.cocina')" texto-enlace="Cocina" class="self-start">
            <p class="-mt-2 mb-3 text-xs text-tinta">Cajas en comandas aceptadas, por variedad.</p>
            @php($maximo = max(1, $this->aProducir->max('cajas')))
            <ul class="space-y-3">
                @forelse ($this->aProducir as $fila)
                    <li class="flex items-center gap-3">
                        <x-tienda.miniatura :producto="$fila['product']" class="{{ $fila['product']->ilustracion === 'bollos' ? 'w-8' : 'size-7' }} shrink-0" />
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold leading-tight">{{ $fila['product']->nombre }}</p>
                            <div class="mt-1.5 h-1.5 rounded-full bg-arena">
                                <div class="h-full rounded-full bg-tomate" style="width: {{ $fila['cajas'] / $maximo * 100 }}%"></div>
                            </div>
                        </div>
                        <span class="w-8 text-right font-display text-2xl">{{ $fila['cajas'] }}</span>
                    </li>
                @empty
                    <li class="rounded-xl bg-harina p-6 text-center text-sm text-tinta">No hay comandas pendientes.</li>
                @endforelse
            </ul>
        </x-admin.tarjeta>

        {{-- Entregas de hoy --}}
        <x-admin.tarjeta titulo="Entregas de hoy" :enlace="route('admin.entregas')" texto-enlace="Reparto" class="self-start">
            <div class="-mx-5 divide-y divide-linea border-y border-linea">
                @forelse ($this->entregasDeHoy as $order)
                    <a href="{{ route('admin.pedido', $order) }}" wire:navigate wire:key="entrega-{{ $order->id }}" class="flex items-center gap-3 px-5 py-3 text-sm hover:bg-harina/60">
                        <span class="w-12 font-bold">{{ $order->numero() }}</span>
                        <span class="min-w-0 flex-1 font-semibold leading-tight">{{ $order->customer->razon_social }}</span>
                        <span class="w-14 text-tinta">{{ $order->franja->etiqueta() }}</span>
                        <x-admin.estado :estado="$order->estado" class="hidden sm:inline-block" />
                        <x-admin.pago :order="$order" />
                    </a>
                @empty
                    <p class="px-5 py-6 text-center text-sm text-tinta">No hay entregas programadas para hoy.</p>
                @endforelse
            </div>

            @foreach ($vencidos as $order)
                <div class="mt-4 rounded-r-lg border-l-4 border-tomate bg-tomate-claro px-4 py-3 text-sm">
                    <p class="font-bold text-tomate">Pago vencido</p>
                    <p>{{ $order->customer->razon_social }} · {{ $order->numero() }} {{ $order->estado === EstadoPedido::Entregado ? 'entregado' : '' }} sin pago registrado.</p>
                </div>
            @endforeach
        </x-admin.tarjeta>
    </div>
</div>
