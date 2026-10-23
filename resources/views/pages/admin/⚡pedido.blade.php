<?php

use App\Enums\EstadoPedido;
use App\Models\Order;
use App\Models\Product;
use App\Support\Formato;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public Order $order;

    public ?string $camion = null;

    /** @var list<string> */
    public array $camiones = ['Camión 1 · Ruta oeste', 'Camión 2 · Ruta sur'];

    public function mount(Order $order): void
    {
        $this->order = $order->load(['customer', 'items.product', 'events']);
        $this->camion = $order->camion;
    }

    public function avanzar(): void
    {
        $this->order->avanzar(auth()->user()->firma());
        $this->refrescar();
    }

    public function rechazar(): void
    {
        $this->order->rechazar(auth()->user()->firma());
        $this->refrescar();
    }

    public function registrarPago(): void
    {
        $this->order->registrarPago(auth()->user()->firma());
        $this->refrescar();
    }

    public function updatedCamion(?string $camion): void
    {
        if ($camion !== null && ! in_array($camion, $this->camiones, true)) {
            return;
        }

        $this->order->update(['camion' => $camion ?: null]);
        $this->order->registrar(auth()->user()->firma(), $camion ? "Asignó el pedido al {$camion}." : 'Quitó el camión asignado.');
        $this->refrescar();
    }

    /**
     * Cuánto suma este pedido a la producción de su día de entrega.
     *
     * @return Collection<int, array{product: Product, comprometidas: int, pedido: int}>|null
     */
    #[Computed]
    public function impacto(): ?Collection
    {
        if (! in_array($this->order->estado, [EstadoPedido::Solicitud, ...EstadoPedido::enCocina()], true)) {
            return null;
        }

        $comprometidas = Order::cajasComprometidas($this->order->entrega_fecha, $this->order->id);
        $delPedido = $this->order->items->groupBy('product_id')->map->sum('cajas');

        return Product::whereIn('id', $comprometidas->keys()->merge($delPedido->keys())->unique())
            ->orderBy('orden')
            ->get()
            ->map(fn (Product $product) => [
                'product' => $product,
                'comprometidas' => $comprometidas->get($product->id, 0),
                'pedido' => $delPedido->get($product->id, 0),
            ]);
    }

    protected function refrescar(): void
    {
        $this->order->refresh()->load(['customer', 'items.product', 'events']);
    }

    public function render()
    {
        return $this->view()
            ->layout('layouts::admin', ['migas' => ['Pedidos', $this->order->numero()]])
            ->title('Pedido '.$this->order->numero());
    }
}; ?>

@php
    $circuito = EstadoPedido::circuito();
    $actual = array_search($order->estado, $circuito, true);
    $siguiente = $order->estado->siguiente();
    [$textoEntrega, $clasesEntrega] = match ($order->estado) {
        EstadoPedido::EnReparto => ['En camino', 'bg-carbon text-white'],
        EstadoPedido::Entregado, EstadoPedido::Finalizado => ['Entregado', 'bg-albahaca-claro text-albahaca'],
        EstadoPedido::Rechazado => ['Rechazado', 'border border-linea text-tinta'],
        default => ['Por entregar', 'border border-carbon/30 bg-white text-carbon'],
    };
@endphp

<div>
    <a href="{{ route('admin.pedidos') }}" wire:navigate class="text-xs font-bold uppercase tracking-[0.12em] text-tomate underline underline-offset-4">← Volver a pedidos</a>

    <x-admin.encabezado :titulo="'Pedido '.$order->numero()">
        <x-slot:bajada>
            <p class="mt-2 text-sm text-tinta">{{ $order->customer->razon_social }} · {{ $order->customer->descripcion() }} · CUIT {{ $order->customer->cuitFormateado() }}</p>
        </x-slot:bajada>
        <x-slot:acciones>
            <button type="button" x-on:click="window.print()" class="tienda-btn-borde !py-2.5">Imprimir comanda</button>
            <x-admin.boton-pronto>Remito</x-admin.boton-pronto>
            @if ($order->estado === EstadoPedido::Solicitud)
                <button type="button" wire:click="rechazar" wire:confirm="¿Rechazar el pedido {{ $order->numero() }}?" class="tienda-btn-borde !py-2.5">Rechazar</button>
            @endif
            @if ($siguiente && ! in_array($siguiente, [EstadoPedido::Entregado, EstadoPedido::Finalizado]))
                <button type="button" wire:click="avanzar" class="tienda-btn-primario !py-2.5">{{ $order->estado->accion() }}</button>
            @endif
        </x-slot:acciones>
    </x-admin.encabezado>

    {{-- Recorrido del pedido --}}
    @if ($order->estado === EstadoPedido::Rechazado)
        <div class="rounded-2xl border border-linea bg-white p-5 text-sm">
            <x-admin.estado :estado="$order->estado" /> el {{ Formato::fecha($order->rechazado_at, 'D j M · H:i') }}.
        </div>
    @else
        <ol class="flex overflow-x-auto rounded-2xl border border-linea bg-white px-4 py-5">
            @foreach ($circuito as $i => $paso)
                @php($fecha = $order->{$paso->columnaFecha()})
                <li class="relative flex min-w-28 flex-1 flex-col items-center text-center">
                    @if (! $loop->first)
                        <span @class(['absolute right-1/2 top-3 h-0.5 w-full', 'bg-albahaca' => $i <= $actual, 'bg-arena' => $i > $actual])></span>
                    @endif
                    <span @class([
                        'relative flex size-6 items-center justify-center rounded-full border-2',
                        'border-albahaca bg-albahaca text-white' => $i < $actual || ($i === $actual && $paso === EstadoPedido::Finalizado),
                        'border-tomate bg-white' => $i === $actual && $paso !== EstadoPedido::Finalizado,
                        'border-arena bg-white' => $i > $actual,
                    ])>
                        @if ($i < $actual || ($i === $actual && $paso === EstadoPedido::Finalizado))
                            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5L20 7" /></svg>
                        @elseif ($i === $actual)
                            <span class="size-2.5 rounded-full bg-tomate"></span>
                        @endif
                    </span>
                    <span @class(['mt-2 text-sm font-bold', 'text-tomate' => $i === $actual && $paso !== EstadoPedido::Finalizado])>{{ $paso === EstadoPedido::Solicitud ? 'Solicitado' : $paso->etiquetaCorta() }}</span>
                    <span class="text-xs text-tinta">
                        @if ($fecha && $i <= $actual)
                            {{ $fecha->isToday() ? 'Hoy '.$fecha->format('H:i') : Formato::fecha($fecha, 'D j · H:i') }}
                        @else
                            —
                        @endif
                    </span>
                </li>
            @endforeach
        </ol>
    @endif

    <div class="mt-4 grid gap-4 xl:grid-cols-[1fr_22rem]">
        <div class="space-y-4">
            {{-- Detalle para cocina --}}
            <x-admin.tarjeta titulo="Detalle para cocina">
                <x-slot:extra>
                    <x-admin.badge>{{ $order->cajas() }} cajas · {{ $order->items->count() }} {{ $order->items->count() === 1 ? 'gusto' : 'gustos' }}</x-admin.badge>
                </x-slot:extra>

                <div class="-mx-5 overflow-x-auto">
                    <table class="w-full min-w-[40rem] text-sm">
                        <thead class="border-y-2 border-carbon text-left text-[0.65rem] font-bold uppercase tracking-[0.12em] text-tinta">
                            <tr>
                                <th class="py-2.5 pl-5"></th>
                                <th class="px-3 py-2.5">Variedad / gusto</th>
                                <th class="px-3 py-2.5 text-center">Cajas</th>
                                <th class="px-3 py-2.5 text-center">Unidades</th>
                                <th class="px-3 py-2.5">Estado</th>
                                <th class="px-5 py-2.5 text-right">Importe</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-linea">
                            @foreach ($order->items as $item)
                                <tr wire:key="item-{{ $item->id }}">
                                    <td class="py-3 pl-5"><x-tienda.miniatura :producto="$item->product" class="{{ $item->product->ilustracion === 'bollos' ? 'w-12' : 'size-10' }}" /></td>
                                    <td class="px-3 py-3">
                                        <p class="font-bold">{{ $item->product->nombre }}</p>
                                        <p class="text-xs text-tinta">{{ $item->product->codigo }} · {{ $item->product->etiqueta() }} · {{ $item->product->detalle }}</p>
                                    </td>
                                    <td class="px-3 py-3 text-center"><span class="font-display text-2xl">{{ $item->cajas }}</span><span class="block text-xs text-tinta">cajas</span></td>
                                    <td class="px-3 py-3 text-center text-xs text-tinta">{{ $item->cajas }} × {{ $item->product->unidades_por_caja ?? '[N]' }}<span class="block">unidades</span></td>
                                    <td class="px-3 py-3">
                                        @if ($item->listo)
                                            <x-admin.badge clases="bg-albahaca-claro text-albahaca">✓ Listo</x-admin.badge>
                                        @elseif (in_array($order->estado, [EstadoPedido::PorPreparar, EstadoPedido::EnHorno]))
                                            <x-admin.estado :estado="$order->estado" />
                                        @else
                                            <x-admin.badge>Por preparar</x-admin.badge>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-right">{{ Formato::dinero($item->importe(), 'IMPORTE') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <dl class="ml-auto mt-4 max-w-xs space-y-1.5 text-sm">
                    <div class="flex justify-between"><dt>Subtotal de lista</dt><dd class="font-semibold">{{ Formato::dinero($order->subtotal, 'SUBTOTAL') }}</dd></div>
                    <div class="flex justify-between font-semibold text-albahaca"><dt>Descuento {{ $order->nivel }} ({{ $order->cajas() }} cajas)</dt><dd>− {{ Formato::dinero($order->descuento, 'DESCUENTO') }}</dd></div>
                    <div class="flex justify-between"><dt>Envío</dt><dd class="font-semibold">{{ $order->esReparto() ? Formato::dinero($order->envio, 'ENVÍO') : 'Sin cargo' }}</dd></div>
                    <div class="flex justify-between"><dt>IVA</dt><dd class="font-semibold">{{ Formato::dinero($order->iva, 'IVA') }}</dd></div>
                    <div class="flex items-end justify-between border-t-2 border-carbon pt-2"><dt>Total</dt><dd class="font-display text-4xl">{{ Formato::dinero($order->total) }}</dd></div>
                </dl>
            </x-admin.tarjeta>

            {{-- Impacto en cocina --}}
            @if ($this->impacto)
                @php($esSolicitud = $order->estado === EstadoPedido::Solicitud)
                @php($antes = $this->impacto->sum('comprometidas'))
                @php($suma = $this->impacto->sum('pedido'))
                @php($maximo = max(1, $this->impacto->max(fn ($fila) => $fila['comprometidas'] + $fila['pedido'])))
                <x-admin.tarjeta titulo="Impacto en cocina">
                    <x-slot:extra>
                        <a href="{{ route('admin.cocina', ['dia' => $order->entrega_fecha->isToday() || $order->entrega_fecha->isPast() ? 'hoy' : ($order->entrega_fecha->isTomorrow() ? 'manana' : 'proximos')]) }}" wire:navigate class="text-sm font-semibold text-tomate underline underline-offset-4">Ver cocina →</a>
                    </x-slot:extra>

                    <p class="-mt-2 text-sm">
                        Producción del <span class="font-bold">{{ mb_strtolower(Formato::fecha($order->entrega_fecha, 'l j \d\e F')) }}</span>:
                        @if ($esSolicitud)
                            si lo aceptás, pasa de <span class="font-bold">{{ $antes }}</span> a <span class="font-bold">{{ $antes + $suma }} cajas</span>
                            <x-admin.badge clases="bg-queso text-carbon">+{{ $suma }}</x-admin.badge>
                        @else
                            <span class="font-bold">{{ $antes + $suma }} cajas</span>, de las cuales {{ $suma }} son de este pedido.
                        @endif
                    </p>

                    <ul class="mt-4 space-y-3">
                        @foreach ($this->impacto as $fila)
                            @php($totalFila = $fila['comprometidas'] + $fila['pedido'])
                            <li class="flex items-center gap-3">
                                <x-tienda.miniatura :producto="$fila['product']" class="{{ $fila['product']->ilustracion === 'bollos' ? 'w-8' : 'size-7' }} shrink-0" />
                                <div class="min-w-0 flex-1">
                                    <div class="flex justify-between gap-2 text-sm">
                                        <span @class(['leading-tight', 'font-bold' => $fila['pedido'] > 0])>{{ $fila['product']->nombre }}</span>
                                        <span class="shrink-0 text-xs text-tinta">
                                            {{ $fila['comprometidas'] }} en cocina
                                            @if ($fila['pedido'] > 0)
                                                <span class="font-bold text-carbon">{{ $esSolicitud ? '+' : '· ' }}{{ $fila['pedido'] }} {{ $esSolicitud ? '' : 'de este pedido' }}</span>
                                            @endif
                                        </span>
                                    </div>
                                    <div class="mt-1.5 flex h-2 overflow-hidden rounded-full bg-arena">
                                        <div class="h-full bg-carbon/70" style="width: {{ $fila['comprometidas'] / $maximo * 100 }}%"></div>
                                        <div class="h-full {{ $esSolicitud ? 'bg-queso' : 'bg-tomate' }}" style="width: {{ $fila['pedido'] / $maximo * 100 }}%"></div>
                                    </div>
                                </div>
                                <span class="w-8 text-right font-display text-2xl">{{ $totalFila }}</span>
                            </li>
                        @endforeach
                    </ul>

                    <p class="mt-4 flex flex-wrap gap-4 text-xs text-tinta">
                        <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-carbon/70"></span>Otros pedidos aceptados</span>
                        <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm {{ $esSolicitud ? 'bg-queso' : 'bg-tomate' }}"></span>Este pedido</span>
                    </p>
                </x-admin.tarjeta>
            @endif

            {{-- Actividad --}}
            <x-admin.tarjeta titulo="Actividad">
                <ol class="space-y-3 text-sm">
                    @foreach ($order->events as $evento)
                        <li wire:key="evento-{{ $evento->id }}" class="flex gap-4">
                            <span class="w-24 shrink-0 font-bold text-tomate">{{ $evento->created_at->isToday() ? $evento->created_at->format('H:i') : Formato::fecha($evento->created_at, 'D j · H:i') }}</span>
                            <span>
                                <span class="block font-bold">{{ $evento->autor }}</span>
                                <span class="text-tinta">{{ $evento->descripcion }}</span>
                            </span>
                        </li>
                    @endforeach
                </ol>
            </x-admin.tarjeta>
        </div>

        <div class="space-y-4">
            {{-- Entrega --}}
            <x-admin.tarjeta titulo="Entrega">
                <x-slot:extra><x-admin.badge :clases="$clasesEntrega">{{ $textoEntrega }}</x-admin.badge></x-slot:extra>

                <dl class="grid grid-cols-[6.5rem_1fr] gap-x-3 gap-y-2.5 text-sm">
                    <dt class="text-tinta">Modalidad</dt>
                    <dd>{{ $order->esReparto() ? 'Reparto a domicilio · camión refrigerado' : 'Retiro por fábrica' }}</dd>
                    <dt class="text-tinta">Día y franja</dt>
                    <dd class="font-bold">{{ $order->entregaRelativa() }}, {{ Formato::fecha($order->entrega_fecha, 'l j') }} · {{ $order->franja->horario() }}</dd>
                    @if ($order->esReparto())
                        <dt class="text-tinta">Dirección</dt>
                        <dd>{{ $order->direccion }}</dd>
                    @endif
                    @if ($order->indicaciones)
                        <dt class="text-tinta">Indicaciones</dt>
                        <dd>{{ $order->indicaciones }}</dd>
                    @endif
                    @if ($order->esReparto())
                        <dt class="self-center text-tinta">Repartidor</dt>
                        <dd>
                            <select wire:model.live="camion" class="tienda-input !w-auto !py-1.5 font-semibold" @disabled(in_array($order->estado, [EstadoPedido::Entregado, EstadoPedido::Finalizado, EstadoPedido::Rechazado]))>
                                <option value="">Asignar camión</option>
                                @foreach ($camiones as $opcion)
                                    <option value="{{ $opcion }}">{{ $opcion }}</option>
                                @endforeach
                            </select>
                        </dd>
                    @endif
                </dl>

                <button
                    type="button"
                    wire:click="avanzar"
                    @disabled($order->estado !== EstadoPedido::EnReparto)
                    class="tienda-btn-borde mt-4 w-full !py-2.5 disabled:cursor-not-allowed disabled:opacity-50"
                >{{ in_array($order->estado, [EstadoPedido::Entregado, EstadoPedido::Finalizado]) ? '✓ Entregado' : 'Marcar como entregado' }}</button>
            </x-admin.tarjeta>

            {{-- Pago --}}
            <x-admin.tarjeta titulo="Pago">
                <x-slot:extra><x-admin.pago :order="$order" /></x-slot:extra>

                <dl class="grid grid-cols-[6.5rem_1fr] gap-x-3 gap-y-2.5 text-sm">
                    <dt class="text-tinta">Forma de pago</dt>
                    <dd>{{ $order->metodo_pago->etiqueta() }}</dd>
                    <dt class="text-tinta">Comprobante</dt>
                    <dd>{{ $order->customer->comprobante() }}</dd>
                    <dt class="self-center text-tinta">{{ $order->estaPagado() ? 'Pagado' : 'Saldo pendiente' }}</dt>
                    <dd class="font-display text-3xl">{{ Formato::dinero($order->total) }}</dd>
                </dl>

                <div class="mt-4 grid grid-cols-2 gap-2">
                    <x-admin.boton-pronto>Ver comprobante</x-admin.boton-pronto>
                    <button
                        type="button"
                        wire:click="registrarPago"
                        @disabled($order->estaPagado() || $order->estado === EstadoPedido::Rechazado)
                        class="tienda-btn-oscuro !py-2.5 disabled:cursor-not-allowed disabled:opacity-50"
                    >{{ $order->estaPagado() ? '✓ Pago registrado' : 'Registrar pago' }}</button>
                </div>
            </x-admin.tarjeta>

            {{-- Finalizar --}}
            @php($puedeFinalizar = $order->estado === EstadoPedido::Entregado && $order->estaPagado())
            <section @class(['rounded-2xl p-5', 'border-2 border-dashed border-linea bg-arena/50' => ! $puedeFinalizar, 'border border-albahaca bg-albahaca-claro' => $puedeFinalizar])>
                <h2 class="font-bold">Finalizar pedido</h2>
                @if ($order->estado === EstadoPedido::Finalizado)
                    <p class="mt-1 text-sm text-albahaca">Pedido finalizado el {{ Formato::fecha($order->finalizado_at, 'D j M') }}.</p>
                @else
                    <p class="mt-1 text-sm text-tinta">Se habilita cuando el pedido está <span class="underline">entregado</span> y <span class="underline">pagado</span>.</p>
                    <button type="button" wire:click="avanzar" @disabled(! $puedeFinalizar) class="tienda-btn-primario mt-4 w-full !py-2.5 disabled:cursor-not-allowed disabled:bg-transparent disabled:text-tinta/50">Finalizar pedido</button>
                @endif
            </section>
        </div>
    </div>
</div>
