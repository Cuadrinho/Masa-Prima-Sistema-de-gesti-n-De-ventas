<?php

use App\Enums\EstadoPedido;
use App\Models\Order;
use App\Support\Formato;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::admin', ['migas' => ['Pedidos', 'Todos']])] #[Title('Pedidos')] class extends Component {
    use WithPagination;

    #[Url]
    public string $estado = '';

    #[Url]
    public string $q = '';

    /**
     * Pestañas de filtro: clave => [texto, estados].
     *
     * @return array<string, array{0: string, 1: list<EstadoPedido>}>
     */
    public function filtros(): array
    {
        return [
            '' => ['Todos', []],
            'solicitud' => ['Nuevas', [EstadoPedido::Solicitud]],
            'cocina' => ['En cocina', [EstadoPedido::PorPreparar, EstadoPedido::EnHorno]],
            'listo' => ['Listos', [EstadoPedido::Listo]],
            'en_reparto' => ['En reparto', [EstadoPedido::EnReparto]],
            'entregado' => ['Entregados', [EstadoPedido::Entregado]],
            'finalizado' => ['Finalizados', [EstadoPedido::Finalizado]],
        ];
    }

    #[Computed]
    public function contadores(): array
    {
        $porEstado = Order::query()->selectRaw('estado, count(*) as total')->groupBy('estado')->pluck('total', 'estado');

        return collect($this->filtros())->map(fn (array $filtro, string $clave) => $clave === ''
            ? $porEstado->except(EstadoPedido::Rechazado->value)->sum()
            : collect($filtro[1])->sum(fn (EstadoPedido $estado) => $porEstado[$estado->value] ?? 0)
        )->all();
    }

    #[Computed]
    public function pedidos(): LengthAwarePaginator
    {
        $estados = $this->filtros()[$this->estado][1] ?? [];
        $buscar = trim(ltrim($this->q, '#'));

        return Order::with(['customer', 'items.product'])
            ->when($estados !== [], fn (Builder $query) => $query->whereIn('estado', $estados))
            ->when($estados === [], fn (Builder $query) => $query->where('estado', '!=', EstadoPedido::Rechazado))
            ->when($buscar !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->when(ctype_digit($buscar), fn (Builder $query) => $query->orWhere('id', (int) $buscar))
                ->orWhereHas('customer', fn (Builder $query) => $query
                    ->whereLike('razon_social', "%{$buscar}%")
                    ->when(strlen(preg_replace('/\D/', '', $buscar)) >= 4, fn (Builder $query) => $query->orWhere('cuit', 'like', '%'.preg_replace('/\D/', '', $buscar).'%')))
                ->orWhereHas('items.product', fn (Builder $query) => $query->whereLike('codigo', "%{$buscar}%")->orWhereLike('nombre', "%{$buscar}%"))))
            ->latest('id')
            ->paginate(10);
    }

    public function updated(string $propiedad): void
    {
        if (in_array($propiedad, ['estado', 'q'])) {
            $this->resetPage();
        }
    }
}; ?>

<div>
    <x-admin.encabezado antetitulo="Gestión de pedidos" titulo="Pedidos">
        <x-slot:acciones>
            <x-admin.boton-pronto>Filtrar por fecha</x-admin.boton-pronto>
            <x-admin.boton-pronto>Descargar Excel</x-admin.boton-pronto>
            <x-admin.boton-pronto oscuro>+ Pedido manual</x-admin.boton-pronto>
        </x-slot:acciones>
    </x-admin.encabezado>

    <div class="mb-4 flex flex-wrap items-center gap-2">
        @foreach ($this->filtros() as $clave => [$texto])
            <button
                type="button"
                wire:click="$set('estado', '{{ $clave }}')"
                @class([
                    'rounded-full border-[1.5px] px-4 py-2 text-sm font-semibold transition',
                    'border-carbon bg-carbon text-white' => $estado === $clave,
                    'border-linea bg-white hover:border-carbon' => $estado !== $clave,
                ])
            >
                {{ $texto }}
                <span @class(['ml-1', 'text-queso' => $estado === $clave, 'text-tinta' => $estado !== $clave])>{{ $this->contadores[$clave] }}</span>
            </button>
        @endforeach

        <input wire:model.live.debounce.300ms="q" type="search" class="tienda-input ml-auto !w-64 !py-2" placeholder="Buscar pedido, cliente o código">
    </div>

    <div class="overflow-hidden rounded-2xl border border-linea bg-white">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[64rem] text-sm">
                <thead class="border-b-2 border-carbon text-left text-[0.65rem] font-bold uppercase tracking-[0.12em] text-tinta">
                    <tr>
                        <th class="px-5 py-3">Pedido</th>
                        <th class="px-3 py-3">Cliente</th>
                        <th class="px-3 py-3">Variedades y cajas</th>
                        <th class="px-3 py-3 text-center">Cajas</th>
                        <th class="px-3 py-3">Entrega</th>
                        <th class="px-3 py-3">Producción</th>
                        <th class="px-3 py-3">Pago</th>
                        <th class="px-5 py-3 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-linea">
                    @forelse ($this->pedidos as $order)
                        <tr
                            wire:key="pedido-{{ $order->id }}"
                            x-on:click="Livewire.navigate('{{ route('admin.pedido', $order) }}')"
                            @class(['cursor-pointer transition hover:bg-harina/70', 'bg-queso-claro/60' => $order->estado === EstadoPedido::Solicitud])
                        >
                            <td class="px-5 py-3.5">
                                <p class="font-bold">{{ $order->numero() }}</p>
                                <p class="text-xs text-tinta">{{ $order->solicitadoRelativo() }}</p>
                            </td>
                            <td class="px-3 py-3.5">
                                <p class="font-bold">{{ $order->customer->razon_social }}</p>
                                <p class="text-xs text-tinta">{{ $order->customer->descripcion() }}</p>
                            </td>
                            <td class="px-3 py-3.5">
                                <div class="flex max-w-60 flex-wrap gap-1.5">
                                    @foreach ($order->items as $item)
                                        <x-admin.item :item="$item" />
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-3 py-3.5 text-center font-display text-2xl">{{ $order->cajas() }}</td>
                            <td class="px-3 py-3.5">
                                <p class="font-bold">{{ $order->entregaRelativa() }}</p>
                                <p class="text-xs text-tinta">{{ $order->franja->etiqueta() }} · {{ $order->franja->horario() }}</p>
                                @php
                                    [$entrega, $clasesEntrega] = match ($order->estado) {
                                        EstadoPedido::EnReparto => ['En camino', 'bg-carbon text-white'],
                                        EstadoPedido::Entregado, EstadoPedido::Finalizado => ['✓ Entregado', 'bg-albahaca-claro text-albahaca'],
                                        EstadoPedido::Rechazado => ['Rechazado', 'border border-linea text-tinta'],
                                        default => ['Por entregar', 'border border-carbon/30 bg-white text-carbon'],
                                    };
                                @endphp
                                <x-admin.badge :clases="$clasesEntrega" class="mt-1">{{ $entrega }}</x-admin.badge>
                            </td>
                            <td class="px-3 py-3.5"><x-admin.estado :estado="$order->estado" /></td>
                            <td class="px-3 py-3.5">
                                <x-admin.pago :order="$order" />
                                <p class="mt-1 text-xs text-tinta">{{ $order->metodo_pago->etiqueta() }}</p>
                            </td>
                            <td class="px-5 py-3.5 text-right font-bold">{{ Formato::dinero($order->total) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-10 text-center text-tinta">No hay pedidos para mostrar.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-linea px-5 py-3 text-xs text-tinta">
            <div class="flex items-center gap-3">
                <span>Mostrando {{ $this->pedidos->count() }} de {{ $this->pedidos->total() }} pedidos · Página {{ $this->pedidos->currentPage() }} de {{ $this->pedidos->lastPage() }}</span>
                @if ($this->pedidos->hasPages())
                    <button type="button" wire:click="previousPage" @disabled($this->pedidos->onFirstPage()) class="rounded border border-linea px-2 py-1 font-semibold text-carbon disabled:opacity-40">←</button>
                    <button type="button" wire:click="nextPage" @disabled(! $this->pedidos->hasMorePages()) class="rounded border border-linea px-2 py-1 font-semibold text-carbon disabled:opacity-40">→</button>
                @endif
            </div>
            <div class="flex flex-wrap items-center gap-1.5">
                <span class="font-semibold text-carbon">Estados:</span>
                @foreach (EstadoPedido::circuito() as $estadoLeyenda)
                    <x-admin.estado :estado="$estadoLeyenda" />
                @endforeach
            </div>
        </div>
    </div>
</div>
