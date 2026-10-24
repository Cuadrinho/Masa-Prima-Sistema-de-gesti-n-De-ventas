<?php

use App\Enums\EstadoCliente;
use App\Enums\EstadoPedido;
use App\Enums\MetodoPago;
use App\Models\Customer;
use App\Models\Order;
use App\Rules\Cuit;
use App\Support\Formato;
use App\Support\Niveles;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::admin', ['migas' => ['Clientes']])] #[Title('Clientes')] class extends Component {
    use WithPagination;

    #[Url]
    public string $vista = 'todos';

    #[Url]
    public string $nivel = '';

    #[Url]
    public string $zona = '';

    #[Url]
    public string $pago = '';

    public bool $cargando = false;

    public string $razonSocial = '';

    public string $cuit = '';

    public string $tipoComercio = '';

    public string $localidad = '';

    public string $whatsapp = '';

    public string $email = '';

    public string $nivelNuevo = 'inicial';

    /**
     * @param  Builder<Customer>  $query
     */
    protected function filtrarVista(Builder $query, string $vista): void
    {
        $compras = fn (Builder $query) => $query->where('estado', '!=', EstadoPedido::Rechazado);

        match ($vista) {
            'activos' => $query->where('estado', EstadoCliente::Activo)
                ->whereHas('orders', fn (Builder $query) => $compras($query)->where('created_at', '>=', today()->subDays(30))),
            'pendientes' => $query->where('estado', EstadoCliente::Pendiente),
            'sin_comprar' => $query->where('estado', EstadoCliente::Activo)
                ->whereDoesntHave('orders', fn (Builder $query) => $compras($query)->where('created_at', '>=', today()->subWeeks(3))),
            default => $query->where('estado', '!=', EstadoCliente::Rechazado),
        };
    }

    protected function consulta(): Builder
    {
        return Customer::query()
            ->tap(fn (Builder $query) => $this->filtrarVista($query, $this->vista))
            ->when($this->nivel !== '', fn (Builder $query) => $query->where('nivel', $this->nivel))
            ->when($this->zona !== '', fn (Builder $query) => $query->where('localidad', $this->zona))
            ->when($this->pago === 'cuenta_corriente', fn (Builder $query) => $query->whereNotNull('cuenta_corriente_dias'))
            ->when($this->pago !== '' && $this->pago !== 'cuenta_corriente', fn (Builder $query) => $query->whereNull('cuenta_corriente_dias')
                ->where(fn (Builder $query) => $query->where('metodo_pago', $this->pago)
                    ->when($this->pago === MetodoPago::Transferencia->value, fn (Builder $query) => $query->orWhereNull('metodo_pago'))));
    }

    #[Computed]
    public function clientes(): LengthAwarePaginator
    {
        return $this->consulta()
            ->with('orders.items')
            ->withMax(['orders as ultima_compra' => fn (Builder $query) => $query->where('estado', '!=', EstadoPedido::Rechazado)], 'created_at')
            ->orderByRaw('case when estado = ? then 0 else 1 end', [EstadoCliente::Pendiente->value])
            // Postgres solo acepta el alias suelto en el ORDER BY, no dentro de una expresión.
            ->orderByRaw('ultima_compra desc nulls last')
            ->orderBy('razon_social')
            ->paginate(10);
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function contadores(): array
    {
        return collect(['todos', 'activos', 'pendientes', 'sin_comprar'])
            ->mapWithKeys(fn (string $vista) => [$vista => Customer::query()->tap(fn (Builder $query) => $this->filtrarVista($query, $vista))->count()])
            ->all();
    }

    #[Computed]
    public function resumen(): array
    {
        $cuentaCorriente = Order::porCobrar()->with('customer')->where('metodo_pago', MetodoPago::CuentaCorriente)->get();

        return [
            'altaMasVieja' => Customer::where('estado', EstadoCliente::Pendiente)->min('created_at'),
            'saldo' => $cuentaCorriente->contains(fn (Order $order) => $order->total === null) ? null : $cuentaCorriente->sum('total'),
            'clientesConSaldo' => $cuentaCorriente->pluck('customer_id')->unique()->count(),
            'vencidos' => $cuentaCorriente->filter(fn (Order $order) => $order->estaVencido())->pluck('customer_id')->unique()->count(),
        ];
    }

    #[Computed]
    public function zonas(): array
    {
        return Customer::whereNotNull('localidad')->distinct()->orderBy('localidad')->pluck('localidad')->all();
    }

    #[Computed]
    public function maximoCajas(): int
    {
        return max(1, $this->clientes->max(fn (Customer $customer) => $customer->cajasEntre(today()->startOfMonth(), now())));
    }

    public function aprobar(int $id): void
    {
        Customer::where('estado', EstadoCliente::Pendiente)->findOrFail($id)->aprobar();
        $this->limpiar();
    }

    public function rechazar(int $id): void
    {
        Customer::where('estado', EstadoCliente::Pendiente)->findOrFail($id)->rechazar();
        $this->limpiar();
    }

    public function cargar(): void
    {
        $this->validate([
            'razonSocial' => ['required', 'string', 'max:255'],
            'cuit' => ['required', new Cuit, function (string $attribute, mixed $value, Closure $fail) {
                if (Customer::where('cuit', Cuit::normalizar($value))->exists()) {
                    $fail('Ya hay un cliente con este CUIT.');
                }
            }],
            'tipoComercio' => ['nullable', 'string', 'max:100'],
            'localidad' => ['nullable', 'string', 'max:100'],
            'whatsapp' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'nivelNuevo' => ['required', Rule::in(Niveles::claves())],
        ], attributes: ['razonSocial' => 'razón social', 'tipoComercio' => 'rubro', 'nivelNuevo' => 'nivel']);

        $customer = Customer::create([
            'razon_social' => $this->razonSocial,
            'cuit' => Cuit::normalizar($this->cuit),
            'tipo_comercio' => $this->tipoComercio ?: null,
            'localidad' => $this->localidad ?: null,
            'whatsapp' => $this->whatsapp ?: null,
            'email' => $this->email ?: null,
            'nivel' => $this->nivelNuevo,
            'estado' => EstadoCliente::Activo,
            'aprobado_at' => now(),
        ]);

        $this->redirectRoute('admin.cliente', $customer, navigate: true);
    }

    public function updated(string $propiedad): void
    {
        if (in_array($propiedad, ['vista', 'nivel', 'zona', 'pago'])) {
            $this->resetPage();
        }
    }

    protected function limpiar(): void
    {
        unset($this->clientes, $this->contadores, $this->resumen);
    }
}; ?>

<div>
    <x-admin.encabezado titulo="Clientes">
        <x-slot:bajada>
            <p class="mt-2 text-sm text-tinta">Comercios con cuenta mayorista.</p>
        </x-slot:bajada>
        <x-slot:acciones>
            <x-admin.boton-pronto>Descargar Excel</x-admin.boton-pronto>
            <button type="button" wire:click="$set('cargando', true)" class="tienda-btn-oscuro !py-2.5">+ Cargar cliente</button>
        </x-slot:acciones>
    </x-admin.encabezado>

    <div class="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.kpi titulo="Clientes activos" :valor="$this->contadores['activos']" detalle="compraron en los últimos 30 días" />
        <x-admin.kpi
            titulo="Altas para aprobar"
            :valor="$this->contadores['pendientes']"
            :detalle="$this->resumen['altaMasVieja'] ? 'la más antigua, '.Carbon\CarbonImmutable::parse($this->resumen['altaMasVieja'])->locale('es')->diffForHumans() : 'no hay altas pendientes'"
            :destacado="$this->contadores['pendientes'] > 0"
        />
        <x-admin.kpi titulo="Sin comprar" :valor="$this->contadores['sin_comprar']" detalle="hace más de 3 semanas" />
        <x-admin.kpi
            titulo="Saldo en cuenta corriente"
            :valor="Formato::dinero($this->resumen['saldo'])"
            :detalle="$this->resumen['clientesConSaldo'].' '.($this->resumen['clientesConSaldo'] === 1 ? 'cliente' : 'clientes').', '.$this->resumen['vencidos'].' '.($this->resumen['vencidos'] === 1 ? 'vencido' : 'vencidos')"
        />
    </div>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-2">
            @foreach (['todos' => 'Todos', 'activos' => 'Activos', 'pendientes' => 'Altas pendientes', 'sin_comprar' => 'Sin comprar'] as $clave => $texto)
                <button type="button" wire:click="$set('vista', '{{ $clave }}')" @class([
                    'rounded-full border px-4 py-2 text-sm font-semibold',
                    'border-carbon bg-carbon text-white' => $vista === $clave,
                    'border-linea bg-white text-carbon hover:border-carbon' => $vista !== $clave,
                ])>
                    {{ $texto }} <span @class(['ml-1', 'text-queso' => $vista === $clave, 'text-tinta' => $vista !== $clave])>{{ $this->contadores[$clave] }}</span>
                </button>
            @endforeach
        </div>
        <div class="flex flex-wrap gap-2 text-sm">
            <select wire:model.live="nivel" class="tienda-input !w-auto !py-2 font-semibold" aria-label="Nivel">
                <option value="">Nivel: todos</option>
                @foreach (Niveles::claves() as $clave)
                    <option value="{{ $clave }}">{{ Niveles::nombre($clave) }}</option>
                @endforeach
            </select>
            <select wire:model.live="zona" class="tienda-input !w-auto !py-2 font-semibold" aria-label="Zona">
                <option value="">Zona: todas</option>
                @foreach ($this->zonas as $localidad)
                    <option value="{{ $localidad }}">{{ $localidad }}</option>
                @endforeach
            </select>
            <select wire:model.live="pago" class="tienda-input !w-auto !py-2 font-semibold" aria-label="Forma de pago">
                <option value="">Forma de pago</option>
                @foreach (MetodoPago::cases() as $metodo)
                    <option value="{{ $metodo->value }}">{{ $metodo->etiqueta() }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-linea bg-white">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[60rem] text-sm">
                <thead class="border-b-2 border-carbon text-left text-[0.65rem] font-bold uppercase tracking-[0.12em] text-tinta">
                    <tr>
                        <th class="px-4 py-3">Cliente</th>
                        <th class="px-3 py-3">CUIT</th>
                        <th class="px-3 py-3">Nivel</th>
                        <th class="px-3 py-3">Pago</th>
                        <th class="px-3 py-3">Última compra</th>
                        <th class="px-3 py-3">Cajas este mes</th>
                        <th class="px-3 py-3">Estado y saldo</th>
                        <th class="px-4 py-3"><span class="sr-only">Acciones</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-linea">
                    @forelse ($this->clientes as $customer)
                        @php $pendiente = $customer->estado === EstadoCliente::Pendiente; @endphp
                        <tr wire:key="cliente-{{ $customer->id }}" @class(['bg-queso-claro' => $pendiente])>
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.cliente', $customer) }}" wire:navigate class="flex items-center gap-3">
                                    <span @class(['flex size-9 shrink-0 items-center justify-center rounded-full text-xs font-bold', 'bg-queso text-carbon' => $pendiente, 'bg-carbon text-white' => ! $pendiente])>{{ $customer->iniciales() }}</span>
                                    <span class="min-w-0">
                                        <span class="block font-bold leading-tight hover:underline">{{ $customer->razon_social }}</span>
                                        <span class="text-xs text-tinta">{{ collect([$customer->tipo_comercio, $customer->localidad])->filter()->implode(', ') }}</span>
                                    </span>
                                </a>
                            </td>
                            <td class="whitespace-nowrap px-3 py-3">{{ $customer->cuitFormateado() }}</td>
                            @if ($pendiente)
                                <td colspan="4" class="px-3 py-3 text-xs text-tinta">{{ $customer->nota_alta ?? 'Falta revisar CUIT y zona de reparto.' }}</td>
                                <td class="px-3 py-3"><x-admin.badge :clases="EstadoCliente::Pendiente->clases()">{{ EstadoCliente::Pendiente->etiqueta() }}</x-admin.badge></td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-2">
                                        <button type="button" wire:click="rechazar({{ $customer->id }})" wire:confirm="¿Rechazar el alta de {{ $customer->razon_social }}?" class="tienda-btn-borde !px-3 !py-2">Rechazar</button>
                                        <button type="button" wire:click="aprobar({{ $customer->id }})" class="tienda-btn-primario !px-3 !py-2">Aprobar alta</button>
                                    </div>
                                </td>
                            @else
                                @php
                                    $cajas = $customer->cajasEntre(today()->startOfMonth(), now());
                                    $saldo = $customer->saldo();
                                    $porCobrar = $customer->pedidosPorCobrar()->isNotEmpty();
                                    $vencido = $customer->tieneVencido();
                                    $sinComprar = $customer->sinComprar();
                                @endphp
                                <td class="px-3 py-3"><x-admin.badge :clases="Niveles::clases($customer->nivel)">{{ $customer->nivelNombre() }}</x-admin.badge></td>
                                <td class="px-3 py-3">{{ $customer->formaDePago() }}</td>
                                <td class="px-3 py-3">
                                    <p class="font-bold">{{ $customer->ultimaCompraTexto() }}</p>
                                    <p class="text-xs text-tinta">{{ $sinComprar && $customer->diasEntreCompras() ? 'antes: ' : '' }}{{ $customer->frecuencia() }}</p>
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex items-center gap-2.5">
                                        <span @class(['w-7 font-bold', 'text-tinta' => $cajas === 0])>{{ $cajas }}</span>
                                        @if ($cajas > 0)
                                            <span class="h-1.5 w-14 rounded-full bg-arena">
                                                <span class="block h-full rounded-full bg-tomate" style="width: {{ $cajas / $this->maximoCajas * 100 }}%"></span>
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-3 py-3">
                                    @if ($vencido)
                                        <x-admin.badge clases="border border-tomate bg-white text-tomate">Pago vencido</x-admin.badge>
                                    @elseif ($sinComprar)
                                        <x-admin.badge clases="bg-arena text-tinta">Sin comprar</x-admin.badge>
                                    @else
                                        <x-admin.badge :clases="EstadoCliente::Activo->clases()">Activo</x-admin.badge>
                                    @endif
                                    <p @class(['mt-1 text-xs', 'font-bold text-tomate' => $vencido, 'font-bold' => $porCobrar && ! $vencido, 'text-tinta' => ! $porCobrar])>
                                        {{ $porCobrar ? Formato::dinero($saldo, $vencido ? 'VENCIDO' : 'SALDO') : 'al día' }}
                                    </p>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if ($sinComprar && $customer->whatsapp)
                                        <a href="https://wa.me/{{ preg_replace('/\D/', '', $customer->whatsapp) }}" target="_blank" rel="noopener" class="tienda-btn-borde !px-3 !py-2">Llamar</a>
                                    @else
                                        <a href="{{ route('admin.cliente', $customer) }}" wire:navigate class="tienda-btn-borde !px-3 !py-2">Ver ficha</a>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-10 text-center text-tinta">No hay clientes con estos filtros.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="flex items-center justify-between gap-3 border-t border-linea px-5 py-3 text-xs text-tinta">
            <span>Mostrando {{ $this->clientes->count() }} de {{ $this->clientes->total() }} clientes, ordenados por actividad</span>
            <span class="flex items-center gap-2">
                Página {{ $this->clientes->currentPage() }} de {{ $this->clientes->lastPage() }}
                @if ($this->clientes->hasPages())
                    <button type="button" wire:click="previousPage" @disabled($this->clientes->onFirstPage()) class="rounded border border-linea px-2 py-1 font-semibold text-carbon disabled:opacity-40">←</button>
                    <button type="button" wire:click="nextPage" @disabled(! $this->clientes->hasMorePages()) class="rounded border border-linea px-2 py-1 font-semibold text-carbon disabled:opacity-40">→</button>
                @endif
            </span>
        </div>
    </div>

    {{-- Alta manual de un cliente --}}
    @if ($cargando)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-carbon/50 p-4" wire:keydown.escape.window="$set('cargando', false)">
            <form wire:submit="cargar" class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="font-display text-3xl uppercase">Cargar cliente</h2>
                    <button type="button" wire:click="$set('cargando', false)" class="rounded p-1 text-tinta hover:text-carbon" aria-label="Cerrar">✕</button>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ([
                        ['razonSocial', 'Razón social', 'text', 'sm:col-span-2'],
                        ['cuit', 'CUIT', 'text', ''],
                        ['tipoComercio', 'Rubro', 'text', ''],
                        ['localidad', 'Localidad', 'text', ''],
                        ['whatsapp', 'WhatsApp', 'tel', ''],
                        ['email', 'Email', 'email', 'sm:col-span-2'],
                    ] as [$campo, $texto, $tipo, $clases])
                        <label class="block {{ $clases }}">
                            <span class="tienda-label">{{ $texto }}</span>
                            <input type="{{ $tipo }}" wire:model="{{ $campo }}" class="tienda-input">
                            @error($campo) <span class="mt-1 block text-xs text-tomate">{{ $message }}</span> @enderror
                        </label>
                    @endforeach
                    <label class="block sm:col-span-2">
                        <span class="tienda-label">Nivel de precio</span>
                        <select wire:model="nivelNuevo" class="tienda-input">
                            @foreach (Niveles::claves() as $clave)
                                <option value="{{ $clave }}">{{ Niveles::nombre($clave) }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" wire:click="$set('cargando', false)" class="tienda-btn-borde !py-2.5">Cancelar</button>
                    <button type="submit" class="tienda-btn-oscuro !py-2.5">Cargar cliente</button>
                </div>
            </form>
        </div>
    @endif
</div>
