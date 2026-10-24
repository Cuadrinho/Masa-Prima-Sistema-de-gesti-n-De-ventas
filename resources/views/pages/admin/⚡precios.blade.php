<?php

use App\Models\PriceList;
use App\Models\Product;
use App\Support\Formato;
use App\Support\Niveles;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::admin', ['migas' => ['Catálogo y precios', 'Lista de precios']])] #[Title('Lista de precios')] class extends Component {
    /** A qué productos se aplica el aumento: todo, linea o seleccion. */
    public string $aplicarA = 'linea';

    public string $linea = '';

    /** @var list<int> */
    public array $seleccion = [];

    public string $porcentaje = '8';

    public int $redondeo = 100;

    /** @var list<string> */
    public array $niveles = [];

    public string $vigenteDesde = '';

    public function mount(): void
    {
        $this->linea = array_key_first(config('tienda.categorias'));
        $this->niveles = Niveles::claves();
        $this->vigenteDesde = (PriceList::borrador()?->vigente_desde ?? today()->next('monday'))->toDateString();
    }

    /**
     * @return Collection<string, Collection<int, Product>>
     */
    #[Computed]
    public function lineas(): Collection
    {
        return Product::orderBy('orden')->orderBy('nombre')->get()->groupBy('categoria');
    }

    #[Computed]
    public function vigente(): ?PriceList
    {
        return PriceList::vigente();
    }

    #[Computed]
    public function borrador(): ?PriceList
    {
        return PriceList::borrador();
    }

    /**
     * @return array<int, array<string, string>>
     */
    #[Computed]
    public function preciosVigentes(): array
    {
        return $this->vigente?->mapa() ?? [];
    }

    /**
     * @return array<int, array<string, string>>
     */
    #[Computed]
    public function preciosBorrador(): array
    {
        return $this->borrador?->mapa() ?? $this->preciosVigentes;
    }

    #[Computed]
    public function cambios(): int
    {
        return $this->borrador?->cambiosContra($this->vigente) ?? 0;
    }

    /**
     * Descuento promedio de cada nivel sobre el Inicial en la lista vigente.
     *
     * @return array<string, float|null>
     */
    #[Computed]
    public function descuentos(): array
    {
        return $this->vigente?->descuentos() ?? [];
    }

    public function fijar(int $productId, string $nivel, string $valor): void
    {
        $product = Product::findOrFail($productId);
        abort_unless(in_array($nivel, Niveles::claves(), true), 422);

        $precio = Formato::leerMonto($valor);
        $actual = $this->preciosBorrador[$productId][$nivel] ?? null;

        if ($precio === ($actual === null ? null : (float) $actual)) {
            return;
        }

        PriceList::borradorOCrear()->fijar($product, $nivel, $precio);
        $this->limpiar();
    }

    public function alternarActivo(int $productId): void
    {
        $product = Product::findOrFail($productId);
        $product->update(['activo' => ! $product->activo, 'actualizado_por' => auth()->user()->name]);
        unset($this->lineas);
    }

    public function updatedVigenteDesde(): void
    {
        $this->validateOnly('vigenteDesde', ['vigenteDesde' => ['required', 'date']]);

        $this->borrador?->update(['vigente_desde' => $this->vigenteDesde]);
    }

    public function aplicarAumento(): void
    {
        $this->validate([
            'aplicarA' => ['required', Rule::in(['todo', 'linea', 'seleccion'])],
            'linea' => ['required_if:aplicarA,linea', Rule::in(array_keys(config('tienda.categorias')))],
            'seleccion' => ['required_if:aplicarA,seleccion', 'array'],
            'porcentaje' => ['required', 'numeric', 'between:-90,500'],
            'redondeo' => ['required', Rule::in([1, 10, 50, 100, 500])],
            'niveles' => ['required', 'array', 'min:1'],
            'niveles.*' => [Rule::in(Niveles::claves())],
            'vigenteDesde' => ['required', 'date'],
        ], [
            'seleccion.required_if' => 'Marcá en la tabla los productos a los que se aplica.',
            'niveles.required' => 'Elegí al menos un nivel.',
        ]);

        $productos = $this->lineas->flatten(1)->filter(fn (Product $product) => match ($this->aplicarA) {
            'todo' => true,
            'linea' => $product->categoria === $this->linea,
            'seleccion' => in_array($product->id, $this->seleccion),
        });

        $borrador = PriceList::borradorOCrear();
        $borrador->update(['vigente_desde' => $this->vigenteDesde]);
        $factor = 1 + (float) str_replace(',', '.', $this->porcentaje) / 100;

        foreach ($productos as $product) {
            foreach ($this->niveles as $nivel) {
                // El aumento se calcula sobre la lista vigente, así aplicarlo dos veces no lo duplica.
                $base = $this->preciosVigentes[$product->id][$nivel] ?? $this->preciosBorrador[$product->id][$nivel] ?? null;

                if ($base !== null) {
                    $borrador->fijar($product, $nivel, round($base * $factor / $this->redondeo) * $this->redondeo);
                }
            }
        }

        $this->limpiar();
    }

    public function descartar(): void
    {
        $this->borrador?->delete();
        $this->limpiar();
    }

    public function publicar(): void
    {
        if ($this->borrador === null || $this->cambios === 0) {
            return;
        }

        $this->borrador->publicar(auth()->user()->firma());
        $this->limpiar();
    }

    protected function limpiar(): void
    {
        unset($this->borrador, $this->vigente, $this->preciosVigentes, $this->preciosBorrador, $this->cambios, $this->descuentos);
    }
}; ?>

<div>
    <x-admin.encabezado titulo="Lista de precios">
        <x-slot:bajada>
            <p class="mt-2 text-sm text-tinta">Precio por caja, según el nivel de cada cliente.</p>
        </x-slot:bajada>
        <x-slot:acciones>
            <x-admin.boton-pronto>Listas anteriores</x-admin.boton-pronto>
            <x-admin.boton-pronto>Descargar en PDF</x-admin.boton-pronto>
            <a href="{{ route('admin.producto.nuevo') }}" wire:navigate class="tienda-btn-oscuro !py-2.5">+ Nuevo producto</a>
        </x-slot:acciones>
    </x-admin.encabezado>

    @if ($this->cambios > 0)
        <div class="mb-4 flex flex-wrap items-center gap-4 rounded-xl bg-carbon px-5 py-3.5 text-white">
            <span class="size-4 shrink-0 rounded-full border-2 border-queso bg-queso/60"></span>
            <div class="min-w-0 flex-1 text-sm">
                <p class="font-bold">Tenés {{ $this->cambios }} {{ $this->cambios === 1 ? 'precio modificado' : 'precios modificados' }} sin publicar.</p>
                <p class="text-white/75">
                    @if ($this->vigente)
                        Los clientes siguen viendo la lista vigente, {{ $this->vigente->nombre() }}, desde el {{ Formato::fecha($this->vigente->vigente_desde, 'j \d\e F') }}.
                    @else
                        Todavía no hay una lista publicada: los clientes no ven precios.
                    @endif
                </p>
            </div>
            <div class="flex gap-2">
                <button type="button" wire:click="descartar" wire:confirm="¿Descartar los cambios del borrador?" class="rounded-md border border-white/30 px-4 py-2 text-sm font-semibold hover:bg-white/10">Descartar cambios</button>
                <button type="button" wire:click="publicar" wire:confirm="¿Publicar {{ $this->cambios }} precios? Rigen desde el {{ $this->borrador->vigenteDesdeTexto() }}." class="rounded-md bg-queso px-4 py-2 text-sm font-bold text-carbon hover:brightness-95">Revisar y publicar</button>
            </div>
        </div>
    @elseif ($programada = PriceList::programada())
        <div class="mb-4 rounded-xl border border-queso bg-queso-claro px-5 py-3.5 text-sm">
            <span class="font-bold">{{ $programada->nombre() }} publicada.</span> Rige desde el {{ $programada->vigenteDesdeTexto() }}; hasta entonces se cobra {{ $this->vigente?->nombre() ?? 'sin lista' }}.
        </div>
    @endif

    <div class="grid gap-4 xl:grid-cols-[1fr_17rem]">
        <div class="overflow-hidden rounded-2xl border border-linea bg-white">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[52rem] text-sm">
                    <thead class="border-b-2 border-carbon text-left text-[0.65rem] font-bold uppercase tracking-[0.12em] text-tinta">
                        <tr>
                            <th class="px-4 py-3">Producto</th>
                            <th class="px-3 py-3">Caja</th>
                            <th class="px-3 py-3">Estado</th>
                            @foreach (Niveles::claves() as $nivel)
                                <th class="px-2 py-3">{{ Niveles::nombre($nivel) }}</th>
                            @endforeach
                            <th class="px-4 py-3 text-right">Cambio</th>
                        </tr>
                    </thead>
                    @forelse ($this->lineas as $categoria => $productos)
                        <tbody wire:key="linea-{{ $categoria }}" class="divide-y divide-linea border-b border-linea">
                            <tr class="bg-harina">
                                <td colspan="{{ 4 + count(Niveles::claves()) }}" class="px-4 py-2 text-xs">
                                    <span class="font-bold uppercase tracking-[0.12em] text-tomate">{{ config('tienda.categorias.'.$categoria, ucfirst($categoria)) }}</span>
                                    <span class="ml-2 text-tinta">{{ $productos->count() }} {{ $productos->count() === 1 ? 'producto' : 'productos' }}</span>
                                </td>
                            </tr>
                            @foreach ($productos as $product)
                                @php
                                    $vigentes = $this->preciosVigentes[$product->id] ?? [];
                                    $nuevos = $this->preciosBorrador[$product->id] ?? [];
                                    $base = Niveles::claves()[0];
                                    $cambio = isset($nuevos[$base]) && ($vigentes[$base] ?? null) != $nuevos[$base] ? Formato::variacion($vigentes[$base] ?? null, $nuevos[$base]) : null;
                                @endphp
                                <tr wire:key="producto-{{ $product->id }}" @class(['text-tinta' => ! $product->activo])>
                                    <td class="px-4 py-2.5">
                                        <div class="flex items-center gap-3">
                                            @if ($aplicarA === 'seleccion')
                                                <input type="checkbox" wire:model.live="seleccion" value="{{ $product->id }}" class="size-4 rounded border-linea accent-carbon" aria-label="Seleccionar {{ $product->nombre }}">
                                            @endif
                                            <x-tienda.miniatura :producto="$product" :class="$product->activo ? 'size-10 shrink-0' : 'size-10 shrink-0 opacity-50'" />
                                            <a href="{{ route('admin.producto', $product) }}" wire:navigate class="min-w-0">
                                                <span @class(['block font-bold leading-tight hover:underline', 'text-carbon' => $product->activo])>{{ $product->nombre }}</span>
                                                <span class="text-xs text-tinta">{{ $product->codigo }}</span>
                                            </a>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2.5">{{ $product->unidades_por_caja ? $product->unidades_por_caja.' u.' : '—' }}</td>
                                    <td class="px-3 py-2.5">
                                        <div class="flex items-center gap-2">
                                            <x-admin.interruptor :activo="$product->activo" :etiqueta="'Activar '.$product->nombre" wire:click="alternarActivo({{ $product->id }})" />
                                            <span @class(['text-xs font-semibold', 'text-albahaca' => $product->activo, 'text-tinta' => ! $product->activo])>{{ $product->activo ? 'Activo' : 'Pausado' }}</span>
                                        </div>
                                    </td>
                                    @foreach (Niveles::claves() as $nivel)
                                        @php $modificado = isset($nuevos[$nivel]) && ($vigentes[$nivel] ?? null) != $nuevos[$nivel]; @endphp
                                        <td class="px-2 py-2.5">
                                            <label @class([
                                                'block w-28 rounded-md border px-2.5 py-1.5 focus-within:ring-2 focus-within:ring-carbon/10',
                                                'border-carbon bg-queso-claro ring-1 ring-queso' => $modificado,
                                                'border-linea bg-white' => ! $modificado,
                                                'opacity-50' => ! $product->activo,
                                            ])>
                                                <span class="flex items-center gap-1 font-bold">
                                                    $<input type="text" inputmode="decimal" value="{{ Formato::monto($nuevos[$nivel] ?? null) }}" placeholder="Sin precio" wire:change="fijar({{ $product->id }}, '{{ $nivel }}', $event.target.value)" class="w-full min-w-0 bg-transparent font-bold placeholder:font-semibold placeholder:text-tinta/60 focus:outline-none" aria-label="Precio {{ Niveles::nombre($nivel) }} de {{ $product->nombre }}">
                                                </span>
                                                @if ($modificado)
                                                    <span class="block text-[0.65rem] text-tinta line-through">antes {{ Formato::dinero($vigentes[$nivel] ?? null) }}</span>
                                                @endif
                                            </label>
                                        </td>
                                    @endforeach
                                    <td class="px-4 py-2.5 text-right">
                                        @if ($cambio)
                                            <x-admin.badge clases="bg-queso-claro text-carbon">{{ $cambio }}</x-admin.badge>
                                        @else
                                            <span class="text-tinta">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    @empty
                        <tbody>
                            <tr><td colspan="7" class="px-5 py-10 text-center text-tinta">Todavía no hay productos cargados.</td></tr>
                        </tbody>
                    @endforelse
                </table>
            </div>
        </div>

        <div class="space-y-4">
            <x-admin.tarjeta titulo="Aumento general">
                <form wire:submit="aplicarAumento" class="space-y-4 text-sm">
                    <div>
                        <span class="tienda-label">Aplicar a</span>
                        <div class="grid grid-cols-3 rounded-lg border border-linea p-1 text-xs font-semibold">
                            @foreach (['todo' => 'Todo', 'linea' => 'Una línea', 'seleccion' => 'Selección'] as $clave => $texto)
                                <button type="button" wire:click="$set('aplicarA', '{{ $clave }}')" @class(['rounded-md px-2 py-1.5', 'bg-carbon text-white' => $aplicarA === $clave, 'text-tinta hover:text-carbon' => $aplicarA !== $clave])>{{ $texto }}</button>
                            @endforeach
                        </div>
                        @if ($aplicarA === 'linea')
                            <select wire:model="linea" class="tienda-input mt-2" aria-label="Línea">
                                @foreach (config('tienda.categorias') as $clave => $nombre)
                                    <option value="{{ $clave }}">{{ $nombre }}</option>
                                @endforeach
                            </select>
                        @elseif ($aplicarA === 'seleccion')
                            <p class="mt-2 text-xs text-tinta">{{ count($seleccion) }} {{ count($seleccion) === 1 ? 'producto marcado' : 'productos marcados' }} en la tabla.</p>
                        @endif
                        @error('seleccion') <p class="mt-1 text-xs text-tomate">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <label>
                            <span class="tienda-label">Porcentaje</span>
                            <span class="flex items-center rounded-md border border-linea bg-white px-3 focus-within:border-carbon">
                                <input type="text" inputmode="decimal" wire:model="porcentaje" class="w-full min-w-0 bg-transparent py-2 font-display text-2xl focus:outline-none">
                                <span class="text-lg text-tinta">%</span>
                            </span>
                            @error('porcentaje') <span class="mt-1 block text-xs text-tomate">{{ $message }}</span> @enderror
                        </label>
                        <label>
                            <span class="tienda-label">Redondear a</span>
                            <select wire:model="redondeo" class="tienda-input">
                                @foreach ([1, 10, 50, 100, 500] as $valor)
                                    <option value="{{ $valor }}">$ {{ $valor }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>

                    <fieldset>
                        <legend class="tienda-label">Niveles</legend>
                        <div class="space-y-1.5">
                            @foreach (Niveles::claves() as $nivel)
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" wire:model="niveles" value="{{ $nivel }}" class="size-4 rounded border-linea accent-carbon">
                                    {{ Niveles::nombre($nivel) }}
                                </label>
                            @endforeach
                        </div>
                        @error('niveles') <p class="mt-1 text-xs text-tomate">{{ $message }}</p> @enderror
                    </fieldset>

                    <label class="block">
                        <span class="tienda-label">Vigente desde</span>
                        <input type="date" wire:model.live="vigenteDesde" min="{{ today()->toDateString() }}" class="tienda-input">
                        @error('vigenteDesde') <span class="mt-1 block text-xs text-tomate">{{ $message }}</span> @enderror
                    </label>

                    <button type="submit" class="tienda-btn-oscuro w-full !py-2.5">Aplicar al borrador</button>
                </form>
            </x-admin.tarjeta>

            <x-admin.tarjeta titulo="Niveles">
                <x-slot:extra>
                    <span class="cursor-not-allowed text-sm font-semibold text-tomate underline underline-offset-4 opacity-60" title="Próximamente">Editar</span>
                </x-slot:extra>
                @php $claves = Niveles::claves(); @endphp
                <dl class="divide-y divide-linea text-sm">
                    @foreach ($claves as $i => $nivel)
                        @php
                            $desde = max(1, Niveles::todos()[$nivel]['desde']);
                            $siguiente = $claves[$i + 1] ?? null;
                        @endphp
                        <div class="flex items-center gap-3 py-2.5">
                            <dt><x-admin.badge :clases="Niveles::clases($nivel)">{{ Niveles::nombre($nivel) }}</x-admin.badge></dt>
                            <dd class="flex-1 text-xs leading-tight text-tinta">
                                @if ($siguiente)
                                    de {{ $desde }} a {{ Niveles::todos()[$siguiente]['desde'] - 1 }} cajas
                                @else
                                    {{ $desde }} cajas o más
                                @endif
                            </dd>
                            <dd class="font-bold">
                                @if ($i === 0)
                                    precio base
                                @else
                                    {{ isset($this->descuentos[$nivel]) ? '− '.$this->descuentos[$nivel].' %' : '—' }}
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </x-admin.tarjeta>
        </div>
    </div>
</div>
