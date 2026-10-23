<?php

use App\Enums\EstadoPedido;
use App\Enums\Franja;
use App\Enums\MetodoPago;
use App\Models\Order;
use App\Support\Formato;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::admin', ['migas' => ['Entregas', 'Hoy']])] #[Title('Entregas y pagos')] class extends Component {
    #[Url]
    public string $cobranzas = 'por_cobrar';

    #[Computed]
    public function hojaDeReparto(): Collection
    {
        return Order::with(['customer', 'items'])
            ->whereDate('entrega_fecha', today())
            ->whereIn('estado', [EstadoPedido::PorPreparar, EstadoPedido::EnHorno, EstadoPedido::Listo, EstadoPedido::EnReparto, EstadoPedido::Entregado])
            ->orderBy('id')
            ->get();
    }

    #[Computed]
    public function porCobrar(): Collection
    {
        return Order::with(['customer', 'items'])->porCobrar()->latest('id')->get();
    }

    #[Computed]
    public function listaCobranzas(): Collection
    {
        return match ($this->cobranzas) {
            'pagados' => Order::with(['customer', 'items'])->whereNotNull('pagado_at')->latest('pagado_at')->limit(20)->get(),
            'cuentas' => $this->porCobrar->where('metodo_pago', MetodoPago::CuentaCorriente),
            default => $this->porCobrar,
        };
    }

    #[Computed]
    public function cobradoHoy(): Collection
    {
        return Order::whereDate('pagado_at', today())->get();
    }

    public function avanzar(int $id): void
    {
        Order::findOrFail($id)->avanzar(auth()->user()->firma());
    }

    public function registrarPago(int $id): void
    {
        Order::findOrFail($id)->registrarPago(auth()->user()->firma());
    }
}; ?>

@php
    $pendientes = $this->hojaDeReparto->where('estado', '!=', EstadoPedido::Entregado);
    $enCamino = $pendientes->where('estado', EstadoPedido::EnReparto)->count();
    $entregadosHoy = Order::with('items')->whereDate('entregado_at', today())->get();
    $vencidos = $this->porCobrar->filter->estaVencido()->count();
    $sumar = fn (Collection $pedidos) => $pedidos->contains(fn ($order) => $order->total === null) ? null : $pedidos->sum('total');
    $camion = $this->hojaDeReparto->pluck('camion')->filter()->unique()->first();
@endphp

<div>
    <x-admin.encabezado antetitulo="Logística y cobranzas" titulo="Entregas y pagos">
        <x-slot:acciones>
            <button type="button" x-on:click="window.print()" class="tienda-btn-borde !py-2.5">Imprimir hoja de ruta</button>
            <x-admin.boton-pronto oscuro>Asignar camión</x-admin.boton-pronto>
        </x-slot:acciones>
    </x-admin.encabezado>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.kpi titulo="Por entregar hoy" :valor="$pendientes->count()" :detalle="$enCamino.' en camino · '.($pendientes->count() - $enCamino).' en planta'" />
        <x-admin.kpi titulo="Entregados hoy" :valor="$entregadosHoy->count()" :detalle="$entregadosHoy->sum(fn ($order) => $order->cajas()).' cajas'" />
        <x-admin.kpi titulo="Por cobrar" :valor="Formato::dinero($sumar($this->porCobrar), 'MONTO')" :detalle="$this->porCobrar->count().' pedidos · '.$vencidos.' '.($vencidos === 1 ? 'vencido' : 'vencidos')" destacado />
        <x-admin.kpi titulo="Cobrado hoy" :valor="Formato::dinero($sumar($this->cobradoHoy), 'MONTO')" :detalle="$this->cobradoHoy->count().' '.($this->cobradoHoy->count() === 1 ? 'pago registrado' : 'pagos registrados')" />
    </div>

    <div class="mt-4 grid gap-4 xl:grid-cols-[1fr_1.15fr]">
        {{-- Hoja de reparto --}}
        <x-admin.tarjeta titulo="Hoja de reparto · Hoy" class="self-start">
            @if ($camion)
                <x-slot:extra><x-admin.badge clases="bg-carbon text-white">{{ $camion }}</x-admin.badge></x-slot:extra>
            @endif

            @foreach (Franja::cases() as $franja)
                @php($pedidos = $this->hojaDeReparto->where('franja', $franja))
                @continue($pedidos->isEmpty())
                <h3 class="mb-2 mt-4 text-xs first-of-type:mt-0"><span class="font-bold uppercase tracking-[0.15em] text-tomate">{{ $franja->etiqueta() }}</span> <span class="ml-1 text-tinta">{{ $franja->horario() }}</span></h3>
                <div class="space-y-2">
                    @foreach ($pedidos as $order)
                        <article wire:key="reparto-{{ $order->id }}" class="flex items-center gap-4 rounded-xl border border-linea p-4">
                            <a href="{{ route('admin.pedido', $order) }}" wire:navigate class="w-20 font-display text-3xl hover:underline">{{ $order->numero() }}</a>
                            <div class="min-w-0 flex-1">
                                <p class="font-bold leading-tight">{{ $order->customer->razon_social }}</p>
                                <p class="text-xs text-tinta">{{ $order->customer->localidad }} · {{ $order->cajas() }} cajas</p>
                                <x-admin.pago :order="$order" class="mt-1.5" />
                            </div>
                            <div class="flex flex-col items-end gap-2">
                                @if ($order->estado === EstadoPedido::Entregado)
                                    <x-admin.badge clases="bg-albahaca-claro text-albahaca">✓ Entregado {{ $order->entregado_at?->format('H:i') }}</x-admin.badge>
                                @else
                                    <x-admin.estado :estado="$order->estado" />
                                @endif
                                @if ($order->estado === EstadoPedido::Listo)
                                    <button type="button" wire:click="avanzar({{ $order->id }})" class="tienda-btn-oscuro !px-4 !py-2">Cargar al camión</button>
                                @elseif ($order->estado === EstadoPedido::EnReparto)
                                    <button type="button" wire:click="avanzar({{ $order->id }})" class="tienda-btn-primario !px-4 !py-2">Confirmar entrega</button>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endforeach

            @if ($this->hojaDeReparto->isEmpty())
                <p class="rounded-xl bg-harina p-6 text-center text-sm text-tinta">No hay entregas para hoy.</p>
            @endif
        </x-admin.tarjeta>

        {{-- Cobranzas --}}
        <x-admin.tarjeta titulo="Cobranzas" id="cobranzas" class="self-start">
            <x-slot:extra>
                <div class="flex rounded-lg border border-linea p-1 text-xs font-semibold">
                    @foreach (['por_cobrar' => 'Por cobrar · '.$this->porCobrar->count(), 'pagados' => 'Pagados', 'cuentas' => 'Cuentas corrientes'] as $clave => $texto)
                        <button type="button" wire:click="$set('cobranzas', '{{ $clave }}')" @class(['rounded-md px-2.5 py-1.5', 'bg-carbon text-white' => $cobranzas === $clave, 'text-tinta hover:text-carbon' => $cobranzas !== $clave])>{{ $texto }}</button>
                    @endforeach
                </div>
            </x-slot:extra>

            <div class="-mx-5 overflow-x-auto">
                <table class="w-full min-w-[34rem] text-sm">
                    <thead class="border-b-2 border-carbon text-left text-[0.65rem] font-bold uppercase tracking-[0.12em] text-tinta">
                        <tr>
                            <th class="py-2.5 pl-5">Pedido</th>
                            <th class="px-3 py-2.5">Cliente</th>
                            <th class="px-3 py-2.5">{{ $cobranzas === 'pagados' ? 'Pagado' : 'Vencimiento' }}</th>
                            <th class="px-3 py-2.5 text-right">{{ $cobranzas === 'pagados' ? 'Importe' : 'Saldo' }}</th>
                            <th class="py-2.5 pr-5"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-linea">
                        @forelse ($this->listaCobranzas as $order)
                            <tr wire:key="cobranza-{{ $order->id }}">
                                <td class="py-3 pl-5 font-bold">{{ $order->numero() }}</td>
                                <td class="px-3 py-3">
                                    <p class="font-bold leading-tight">{{ $order->customer->razon_social }}</p>
                                    <p class="text-xs text-tinta">{{ $order->metodo_pago->etiqueta() }} · {{ in_array($order->estado, [EstadoPedido::Entregado, EstadoPedido::Finalizado]) ? 'entregado' : 'por entregar' }}</p>
                                </td>
                                <td class="px-3 py-3">
                                    @if ($cobranzas === 'pagados')
                                        <x-admin.badge clases="bg-albahaca-claro text-albahaca">✓ {{ Formato::fecha($order->pagado_at, 'j M') }}</x-admin.badge>
                                    @else
                                        @php([$texto, $clases] = $order->badgeVencimiento())
                                        <x-admin.badge :clases="$clases">{{ $texto }}</x-admin.badge>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-right font-semibold">{{ Formato::dinero($order->total, 'IMPORTE') }}</td>
                                <td class="py-3 pr-5 text-right">
                                    @if ($cobranzas !== 'pagados' && $order->estado !== EstadoPedido::Solicitud && $order->metodo_pago !== MetodoPago::CuentaCorriente)
                                        <button type="button" wire:click="registrarPago({{ $order->id }})" class="tienda-btn-oscuro !px-3 !py-2 !text-xs">Registrar pago</button>
                                    @else
                                        <a href="{{ route('admin.pedido', $order) }}" wire:navigate class="tienda-btn-borde !px-3 !py-2 !text-xs">{{ $order->metodo_pago === MetodoPago::CuentaCorriente && $order->estado !== EstadoPedido::Solicitud ? 'Ver cuenta' : 'Ver pedido' }}</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-8 text-center text-tinta">Nada para mostrar.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin.tarjeta>
    </div>
</div>
