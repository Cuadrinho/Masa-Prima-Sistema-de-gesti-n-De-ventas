<?php

use App\Enums\EstadoPedido;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\Formato;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::admin', ['migas' => ['Cocina', 'Producción']])] #[Title('Cocina')] class extends Component {
    #[Url]
    public string $dia = 'hoy';

    /**
     * Filtra por el día elegido. "Hoy" incluye los atrasados.
     */
    protected function delDia(Builder $query): Builder
    {
        return match ($this->dia) {
            'manana' => $query->whereDate('entrega_fecha', today()->addDay()),
            'proximos' => $query->whereDate('entrega_fecha', '>', today()->addDay()),
            default => $query->whereDate('entrega_fecha', '<=', today()),
        };
    }

    /**
     * Comandas aceptadas: lo que la cocina tiene que producir.
     */
    #[Computed]
    public function comandas(): Collection
    {
        return $this->delDia(Order::with(['customer', 'items.product']))
            ->whereIn('estado', EstadoPedido::enCocina())
            ->orderBy('entrega_fecha')
            ->orderBy('franja')
            ->orderBy('id')
            ->get();
    }

    /**
     * Solicitudes que todavía no se aceptaron: lo que puede llegar a cocina.
     */
    #[Computed]
    public function solicitudes(): Collection
    {
        return $this->delDia(Order::with(['customer', 'items.product']))
            ->where('estado', EstadoPedido::Solicitud)
            ->orderBy('entrega_fecha')
            ->orderBy('id')
            ->get();
    }

    /**
     * Cajas totales, listas y a confirmar por variedad, agrupadas por categoría.
     */
    #[Computed]
    public function porGusto(): Collection
    {
        $aceptados = $this->comandas->flatMap->items;
        $aConfirmar = $this->solicitudes->flatMap->items;

        return $aceptados->concat($aConfirmar)
            ->groupBy('product_id')
            ->map(fn (Collection $items, int $productId) => [
                'product' => $items->first()->product,
                'cajas' => $aceptados->where('product_id', $productId)->sum('cajas'),
                'listas' => $aceptados->where('product_id', $productId)->where('listo', true)->sum('cajas'),
                'aConfirmar' => $aConfirmar->where('product_id', $productId)->sum('cajas'),
            ])
            ->sortBy(fn (array $fila) => $fila['product']->orden)
            ->groupBy(fn (array $fila) => $fila['product']->categoria);
    }

    public function marcar(int $itemId): void
    {
        $item = OrderItem::with('order')->findOrFail($itemId);

        if (! in_array($item->order->estado, [EstadoPedido::PorPreparar, EstadoPedido::EnHorno])) {
            return;
        }

        $item->update(['listo' => ! $item->listo]);
    }

    public function avanzar(int $id): void
    {
        Order::findOrFail($id)->avanzar(auth()->user()->firma());
    }
}; ?>

<div wire:poll.15s>
    <x-admin.encabezado antetitulo="Vista de cocina · Pantalla de producción" titulo="Producción del día">
        <x-slot:acciones>
            <div class="flex rounded-lg border border-linea bg-white p-1 text-sm font-semibold">
                @foreach ([
                    'hoy' => 'Hoy · '.Formato::fecha(today(), 'D j'),
                    'manana' => 'Mañana · '.Formato::fecha(today()->addDay(), 'D j'),
                    'proximos' => 'Próximos días',
                ] as $clave => $texto)
                    <button type="button" wire:click="$set('dia', '{{ $clave }}')" @class(['rounded-md px-3 py-1.5', 'bg-carbon text-white' => $dia === $clave, 'text-tinta hover:text-carbon' => $dia !== $clave])>{{ $texto }}</button>
                @endforeach
            </div>
        </x-slot:acciones>
    </x-admin.encabezado>

    {{-- Solicitudes por aprobar --}}
    @if ($this->solicitudes->isNotEmpty())
        <section class="mb-4 rounded-2xl border border-queso bg-queso-claro p-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-display text-xl uppercase">
                    Por aprobar · {{ $this->solicitudes->count() }} {{ $this->solicitudes->count() === 1 ? 'solicitud' : 'solicitudes' }}
                    <span class="ml-1 font-body text-sm font-semibold normal-case">(+{{ $this->solicitudes->sum(fn ($order) => $order->cajas()) }} cajas si se aceptan)</span>
                </h2>
                <p class="text-xs text-tinta">Todavía no entran a producción. Se suman cuando el admin las acepta.</p>
            </div>
            <div class="mt-3 flex gap-3 overflow-x-auto pb-1">
                @foreach ($this->solicitudes as $order)
                    <a href="{{ route('admin.pedido', $order) }}" wire:navigate wire:key="solicitud-{{ $order->id }}" class="min-w-60 rounded-xl border border-queso bg-white p-3 hover:shadow-md">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-display text-2xl">{{ $order->numero() }}</span>
                            <x-admin.badge clases="bg-queso text-carbon">{{ $order->entregaRelativa() }} · {{ $order->franja->horario() }}</x-admin.badge>
                        </div>
                        <p class="text-sm font-semibold">{{ $order->customer->razon_social }}</p>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @foreach ($order->items as $item)
                                <x-admin.item :item="$item" />
                            @endforeach
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <div class="grid gap-4 xl:grid-cols-[22rem_1fr]">
        {{-- A producir por gusto --}}
        <x-admin.tarjeta titulo="A producir por gusto" class="self-start">
            @php
                $filas = $this->porGusto->flatten(1);
                $total = $filas->sum('cajas');
                $listas = $filas->sum('listas');
                $aConfirmar = $filas->sum('aConfirmar');
            @endphp
            <div class="grid grid-cols-3 rounded-xl bg-carbon px-4 py-3 text-white">
                <p><span class="block font-display text-3xl">{{ $total }}</span><span class="text-xs text-white/70">cajas totales</span></p>
                <p><span class="block font-display text-3xl">{{ $listas }}</span><span class="text-xs text-white/70">listas</span></p>
                <p><span class="block font-display text-3xl text-queso">{{ $total - $listas }}</span><span class="text-xs text-white/70">faltan</span></p>
            </div>
            @if ($aConfirmar > 0)
                <p class="mt-2 rounded-lg bg-queso-claro px-3 py-2 text-xs"><span class="font-bold">+{{ $aConfirmar }} cajas a confirmar</span> en solicitudes sin aprobar.</p>
            @endif

            @forelse ($this->porGusto as $categoria => $grupo)
                <h3 class="mt-5 text-xs font-bold uppercase tracking-[0.15em] text-tomate">{{ config('tienda.categorias.'.$categoria, $categoria) }}</h3>
                <ul class="divide-y divide-linea">
                    @foreach ($grupo as $fila)
                        @php($faltan = $fila['cajas'] - $fila['listas'])
                        <li class="flex items-center gap-3 py-3">
                            <x-tienda.miniatura :producto="$fila['product']" class="{{ $fila['product']->ilustracion === 'bollos' ? 'w-11' : 'size-10' }} shrink-0" />
                            <div class="min-w-0 flex-1">
                                <p class="text-[0.65rem] font-semibold text-tinta">{{ $fila['product']->codigo }}</p>
                                <p class="text-sm font-bold leading-tight">{{ $fila['product']->nombre }}</p>
                                <div class="mt-1.5 h-1.5 rounded-full bg-arena">
                                    <div class="h-full rounded-full bg-albahaca" style="width: {{ $fila['listas'] / max(1, $fila['cajas']) * 100 }}%"></div>
                                </div>
                                <p class="mt-1 text-xs text-tinta">
                                    {{ $fila['listas'] }} listas · <span class="font-semibold text-tomate">{{ $faltan }} faltan</span>
                                    @if ($fila['aConfirmar'] > 0)
                                        · <span class="rounded bg-queso-claro px-1 font-semibold text-carbon">+{{ $fila['aConfirmar'] }} a confirmar</span>
                                    @endif
                                </p>
                            </div>
                            <p class="text-center leading-none"><span class="block font-display text-3xl">{{ $fila['cajas'] }}</span><span class="text-xs text-tinta">cajas</span></p>
                        </li>
                    @endforeach
                </ul>
            @empty
                <p class="mt-4 rounded-xl bg-harina p-6 text-center text-sm text-tinta">No hay comandas para este día.</p>
            @endforelse
        </x-admin.tarjeta>

        {{-- Tablero de comandas --}}
        <div class="grid gap-4 md:grid-cols-3">
            @foreach ([EstadoPedido::PorPreparar, EstadoPedido::EnHorno, EstadoPedido::Listo] as $columna)
                @php($pedidos = $this->comandas->where('estado', $columna))
                <section class="rounded-2xl bg-arena/70 p-3">
                    <header class="mb-3 flex items-center justify-between rounded-xl px-3 py-2.5 {{ $columna === EstadoPedido::PorPreparar ? 'bg-white' : $columna->clasesTarjeta() }}">
                        <h2 class="font-display text-xl uppercase">{{ $columna->etiqueta() }}</h2>
                        <span class="rounded-md bg-white px-2 text-sm font-bold">{{ $pedidos->count() }}</span>
                    </header>

                    <div class="space-y-3">
                        @forelse ($pedidos as $order)
                            <article wire:key="comanda-{{ $order->id }}" class="rounded-xl border-t-4 border-carbon bg-white p-4 shadow-sm">
                                <div class="flex items-start justify-between gap-2">
                                    <a href="{{ route('admin.pedido', $order) }}" wire:navigate class="font-display text-3xl hover:underline">{{ $order->numero() }}</a>
                                    <x-admin.badge clases="bg-queso text-carbon">{{ $order->entregaRelativa() }} · {{ $order->franja->horario() }}</x-admin.badge>
                                </div>
                                <p class="text-sm font-semibold">{{ $order->customer->razon_social }}</p>

                                <ul class="mt-3 space-y-2">
                                    @foreach ($order->items as $item)
                                        <li wire:key="item-{{ $item->id }}">
                                            <label @class(['flex cursor-pointer items-center gap-3 rounded-lg p-2.5', 'bg-albahaca-claro' => $item->listo, 'bg-harina' => ! $item->listo])>
                                                <input
                                                    type="checkbox"
                                                    wire:click="marcar({{ $item->id }})"
                                                    @checked($item->listo)
                                                    @disabled($columna === EstadoPedido::Listo)
                                                    class="size-4.5 shrink-0 accent-albahaca"
                                                >
                                                <span class="font-display text-2xl">{{ $item->cajas }}</span>
                                                <span @class(['text-sm font-semibold leading-tight', 'text-albahaca line-through' => $item->listo])>
                                                    {{ $item->product->nombre }}
                                                    <span class="block text-xs font-normal text-tinta no-underline">{{ $item->product->codigo }}</span>
                                                </span>
                                            </label>
                                        </li>
                                    @endforeach
                                </ul>

                                <div class="mt-3 flex justify-between border-t-2 border-carbon pt-2 text-sm font-bold">
                                    <span>Total</span>
                                    <span>{{ $order->cajas() }} cajas</span>
                                </div>

                                @if ($order->indicaciones)
                                    <p class="mt-3 rounded-lg bg-queso-claro p-2.5 text-xs">📝 {{ $order->indicaciones }}</p>
                                @endif

                                <button
                                    type="button"
                                    wire:click="avanzar({{ $order->id }})"
                                    @class([
                                        'mt-3 w-full !py-2.5',
                                        'tienda-btn-primario' => $columna === EstadoPedido::PorPreparar,
                                        'tienda-btn-oscuro' => $columna === EstadoPedido::EnHorno,
                                        'tienda-btn-borde' => $columna === EstadoPedido::Listo,
                                    ])
                                >{{ $order->estado->accion() }}</button>
                            </article>
                        @empty
                            <p class="rounded-xl border-2 border-dashed border-linea p-6 text-center text-sm text-tinta">Sin comandas.</p>
                        @endforelse
                    </div>
                </section>
            @endforeach
        </div>
    </div>
</div>
