<?php

use App\Enums\EstadoPedido;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\Formato;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::admin', ['migas' => ['Finalizados']])] #[Title('Pedidos finalizados')] class extends Component {
    use WithPagination;

    #[Url]
    public string $periodo = 'semana';

    #[Url]
    public ?string $desde = null;

    #[Url]
    public ?string $hasta = null;

    /**
     * @return array{0: Carbon\CarbonInterface, 1: Carbon\CarbonInterface}
     */
    protected function rango(): array
    {
        return match ($this->periodo) {
            'hoy' => [today(), now()->endOfDay()],
            'mes' => [today()->subDays(29), now()->endOfDay()],
            'rango' => [
                rescue(fn () => Carbon\CarbonImmutable::parse($this->desde)->startOfDay(), today()->subDays(6), false),
                rescue(fn () => Carbon\CarbonImmutable::parse($this->hasta)->endOfDay(), now()->endOfDay(), false),
            ],
            default => [today()->subDays(6), now()->endOfDay()],
        };
    }

    protected function consulta(): Builder
    {
        return Order::query()
            ->where('estado', EstadoPedido::Finalizado)
            ->whereBetween('finalizado_at', $this->rango());
    }

    #[Computed]
    public function pedidos(): LengthAwarePaginator
    {
        return $this->consulta()->with(['customer', 'items.product'])->latest('finalizado_at')->paginate(10);
    }

    #[Computed]
    public function resumen(): array
    {
        $pedidos = $this->consulta()->with('items')->get();

        return [
            'pedidos' => $pedidos->count(),
            'cajas' => $pedidos->sum(fn (Order $order) => $order->cajas()),
            'facturado' => $pedidos->contains(fn (Order $order) => $order->total === null) ? null : $pedidos->sum('total'),
            'niveles' => collect(config('tienda.niveles'))->mapWithKeys(fn (array $nivel) => [$nivel['nombre'] => $pedidos->where('nivel', $nivel['nombre'])->count()]),
        ];
    }

    #[Computed]
    public function masVendidos(): Collection
    {
        return OrderItem::with('product')
            ->whereIn('order_id', $this->consulta()->select('id'))
            ->get()
            ->groupBy('product_id')
            ->map(fn (Collection $items) => ['product' => $items->first()->product, 'cajas' => $items->sum('cajas')])
            ->sortByDesc('cajas')
            ->take(5)
            ->values();
    }

    public function updated(string $propiedad): void
    {
        $this->resetPage();
    }
}; ?>

<div>
    <x-admin.encabezado antetitulo="Historial" titulo="Pedidos finalizados">
        <x-slot:acciones>
            <div class="flex rounded-lg border border-linea bg-white p-1 text-sm font-semibold">
                @foreach (['hoy' => 'Hoy', 'semana' => 'Esta semana', 'mes' => 'Mes', 'rango' => 'Rango…'] as $clave => $texto)
                    <button type="button" wire:click="$set('periodo', '{{ $clave }}')" @class(['rounded-md px-3 py-1.5', 'bg-carbon text-white' => $periodo === $clave, 'text-tinta hover:text-carbon' => $periodo !== $clave])>{{ $texto }}</button>
                @endforeach
            </div>
            <x-admin.boton-pronto>Descargar Excel</x-admin.boton-pronto>
        </x-slot:acciones>
    </x-admin.encabezado>

    @if ($periodo === 'rango')
        <div class="-mt-2 mb-4 flex flex-wrap items-end gap-3">
            <label class="text-sm"><span class="tienda-label">Desde</span><input type="date" wire:model.live="desde" class="tienda-input !py-2"></label>
            <label class="text-sm"><span class="tienda-label">Hasta</span><input type="date" wire:model.live="hasta" class="tienda-input !py-2"></label>
        </div>
    @endif

    <div class="grid gap-4 xl:grid-cols-[1fr_20rem]">
        <div class="overflow-hidden rounded-2xl border border-linea bg-white">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[56rem] text-sm">
                    <thead class="border-b-2 border-carbon text-left text-[0.65rem] font-bold uppercase tracking-[0.12em] text-tinta">
                        <tr>
                            <th class="px-5 py-3">Pedido</th>
                            <th class="px-3 py-3">Cliente</th>
                            <th class="px-3 py-3">Variedades</th>
                            <th class="px-3 py-3 text-center">Cajas</th>
                            <th class="px-3 py-3">Entrega</th>
                            <th class="px-3 py-3">Pago</th>
                            <th class="px-5 py-3 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-linea">
                        @forelse ($this->pedidos as $order)
                            <tr wire:key="finalizado-{{ $order->id }}">
                                <td class="px-5 py-3.5">
                                    <a href="{{ route('admin.pedido', $order) }}" wire:navigate class="font-bold hover:underline">{{ $order->numero() }}</a>
                                    <p class="text-xs text-tinta">Cerrado</p>
                                    <p class="text-xs text-tinta">{{ $order->solicitadoRelativo() }}</p>
                                </td>
                                <td class="px-3 py-3.5">
                                    <p class="font-bold">{{ $order->customer->razon_social }}</p>
                                    <p class="text-xs text-tinta">{{ $order->customer->descripcion() }}</p>
                                    <div class="mt-1.5 flex gap-1.5">
                                        <button type="button" disabled title="Próximamente" class="cursor-not-allowed rounded border border-linea px-2 py-0.5 text-xs font-semibold opacity-60">Factura</button>
                                        <button type="button" disabled title="Próximamente" class="cursor-not-allowed rounded border border-linea px-2 py-0.5 text-xs font-semibold opacity-60">Remito</button>
                                    </div>
                                </td>
                                <td class="px-3 py-3.5">
                                    <div class="flex flex-col items-start gap-1">
                                        @foreach ($order->items as $item)
                                            <x-admin.item :item="$item" codigo />
                                        @endforeach
                                    </div>
                                </td>
                                <td class="px-3 py-3.5 text-center font-display text-2xl">{{ $order->cajas() }}</td>
                                <td class="px-3 py-3.5">
                                    <p>{{ Formato::fecha($order->entrega_fecha) }}</p>
                                    <p class="text-xs font-semibold text-albahaca">✓ Entregado</p>
                                </td>
                                <td class="px-3 py-3.5">
                                    <p>{{ $order->metodo_pago->etiqueta() }}</p>
                                    <p class="text-xs font-semibold text-albahaca">✓ Pagado</p>
                                </td>
                                <td class="px-5 py-3.5 text-right font-bold">{{ Formato::dinero($order->total) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-10 text-center text-tinta">No hay pedidos finalizados en este período.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="flex items-center justify-between gap-3 border-t border-linea px-5 py-3 text-xs text-tinta">
                <span>Mostrando {{ $this->pedidos->count() }} de {{ $this->pedidos->total() }} pedidos finalizados · entregados y cobrados</span>
                <span class="flex items-center gap-2">
                    Página {{ $this->pedidos->currentPage() }} de {{ $this->pedidos->lastPage() }}
                    @if ($this->pedidos->hasPages())
                        <button type="button" wire:click="previousPage" @disabled($this->pedidos->onFirstPage()) class="rounded border border-linea px-2 py-1 font-semibold text-carbon disabled:opacity-40">←</button>
                        <button type="button" wire:click="nextPage" @disabled(! $this->pedidos->hasMorePages()) class="rounded border border-linea px-2 py-1 font-semibold text-carbon disabled:opacity-40">→</button>
                    @endif
                </span>
            </div>
        </div>

        <div class="space-y-4">
            <div class="rounded-2xl bg-carbon p-5 text-white">
                <p class="text-[0.65rem] font-bold uppercase tracking-[0.15em] text-white/75">Cerrado en el período</p>
                <p class="mt-2 font-display text-5xl">{{ $this->resumen['cajas'] }} cajas</p>
                <p class="text-xs text-white/75">{{ $this->resumen['pedidos'] }} pedidos · {{ Formato::dinero($this->resumen['facturado'], 'FACTURADO') }}</p>
            </div>

            <x-admin.tarjeta titulo="Gustos más vendidos">
                @php($maximo = max(1, $this->masVendidos->max('cajas')))
                <ul class="space-y-3">
                    @forelse ($this->masVendidos as $fila)
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
                        <li class="text-sm text-tinta">Sin ventas en el período.</li>
                    @endforelse
                </ul>
            </x-admin.tarjeta>

            <x-admin.tarjeta titulo="Por nivel">
                <dl class="divide-y divide-linea text-sm">
                    @foreach ($this->resumen['niveles'] as $nivel => $cantidad)
                        <div class="flex justify-between py-2.5"><dt>{{ $nivel }}</dt><dd class="font-bold">{{ $cantidad }}</dd></div>
                    @endforeach
                </dl>
            </x-admin.tarjeta>
        </div>
    </div>
</div>
