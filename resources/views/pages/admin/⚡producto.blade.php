<?php

use App\Models\PriceList;
use App\Models\Product;
use App\Support\Formato;
use App\Support\Niveles;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public ?Product $product = null;

    public string $nombre = '';

    public string $codigo = '';

    public string $categoria = '';

    public string $unidades = '';

    public string $tamano = '';

    public string $descripcion = '';

    /** @var list<string> */
    public array $etiquetas = [];

    public string $nuevaEtiqueta = '';

    public bool $agregandoEtiqueta = false;

    public bool $visibleWeb = true;

    public bool $sePuedePedir = true;

    /** @var list<int> 1 (lunes) a 7 (domingo) */
    public array $dias = [1, 2, 3, 4, 5];

    /** @var array<string, string> nivel => precio tipeado */
    public array $precios = [];

    /** @var Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $foto = null;

    public bool $quitarFoto = false;

    public function mount(?Product $product = null): void
    {
        $this->product = $product?->exists ? $product : null;
        $this->categoria = array_key_first(config('tienda.categorias'));

        if ($product = $this->product) {
            $this->nombre = $product->nombre;
            $this->codigo = $product->codigo;
            $this->categoria = $product->categoria;
            $this->unidades = (string) $product->unidades_por_caja;
            $this->tamano = (string) $product->tamano_cm;
            $this->descripcion = (string) $product->descripcion;
            $this->etiquetas = $product->etiquetas ?? [];
            $this->visibleWeb = $product->visible_web;
            $this->sePuedePedir = $product->se_puede_pedir;
            $this->dias = $product->dias_produccion ?? $this->dias;
        }

        foreach (Niveles::claves() as $nivel) {
            $this->precios[$nivel] = Formato::monto($this->preciosActuales[$nivel] ?? null);
        }
    }

    public function render()
    {
        return $this->view()
            ->layout('layouts::admin', ['migas' => ['Catálogo y precios', $this->product?->codigo ?? 'Nuevo producto']])
            ->title($this->product?->nombre ?? 'Nuevo producto');
    }

    /**
     * @return array<string, string> nivel => precio
     */
    #[Computed]
    public function preciosVigentes(): array
    {
        return $this->product ? (PriceList::mapaVigente()[$this->product->id] ?? []) : [];
    }

    /**
     * Precios del borrador si hay uno, si no los vigentes.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function preciosActuales(): array
    {
        $borrador = PriceList::borrador();

        return $borrador && $this->product ? ($borrador->mapa()[$this->product->id] ?? []) : $this->preciosVigentes;
    }

    #[Computed]
    public function borrador(): ?PriceList
    {
        return PriceList::borrador();
    }

    /**
     * Precio Inicial de las últimas listas publicadas, con la variación contra la anterior.
     *
     * @return Collection<int, array{lista: PriceList, precio: string|null, cambio: string|null}>
     */
    #[Computed]
    public function historial(): Collection
    {
        if (! $this->product) {
            return collect();
        }

        $base = Niveles::claves()[0];
        $listas = PriceList::publicadas()
            ->with(['prices' => fn ($query) => $query->where('product_id', $this->product->id)->where('nivel', $base)])
            ->take(6)
            ->get();

        return $listas->take(5)->values()->map(function (PriceList $lista, int $i) use ($listas) {
            $precio = $lista->prices->first()?->precio;
            $anterior = $listas->get($i + 1)?->prices->first()?->precio;

            return ['lista' => $lista, 'precio' => $precio, 'cambio' => Formato::variacion($anterior, $precio)];
        });
    }

    public function agregarEtiqueta(): void
    {
        $etiqueta = trim($this->nuevaEtiqueta);

        if ($etiqueta !== '' && ! in_array($etiqueta, $this->etiquetas, true) && count($this->etiquetas) < 6) {
            $this->etiquetas[] = Str::limit($etiqueta, 30, '');
        }

        $this->nuevaEtiqueta = '';
        $this->agregandoEtiqueta = false;
    }

    public function quitarEtiqueta(int $indice): void
    {
        unset($this->etiquetas[$indice]);
        $this->etiquetas = array_values($this->etiquetas);
    }

    public function alternarDia(int $dia): void
    {
        $this->dias = in_array($dia, $this->dias)
            ? array_values(array_diff($this->dias, [$dia]))
            : collect([...$this->dias, $dia])->sort()->values()->all();
    }

    public function updatedFoto(): void
    {
        $this->quitarFoto = false;
    }

    public function quitarImagen(): void
    {
        $this->foto = null;
        $this->quitarFoto = true;
    }

    public function alternarPausa(): void
    {
        if (! $this->product) {
            return;
        }

        $this->product->update(['activo' => ! $this->product->activo, 'actualizado_por' => auth()->user()->name]);
    }

    public function guardar(bool $publicar = false): void
    {
        $this->codigo = mb_strtoupper(trim($this->codigo));

        $this->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'codigo' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9-]+$/', Rule::unique('products', 'codigo')->ignore($this->product)],
            'categoria' => ['required', Rule::in(array_keys(config('tienda.categorias')))],
            'unidades' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'tamano' => ['nullable', 'integer', 'min:1', 'max:200'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'etiquetas' => ['array', 'max:6'],
            'dias' => ['array'],
            'dias.*' => ['integer', 'between:1,7'],
            'foto' => ['nullable', 'image', 'max:4096'],
            'precios.*' => ['nullable', function (string $attribute, mixed $value, Closure $fail) {
                if (filled($value) && Formato::leerMonto($value) === null) {
                    $fail('Escribí el precio como número, por ejemplo 12.500.');
                }
            }],
        ], [
            'codigo.regex' => 'Usá letras, números y guiones, por ejemplo PZ-06.',
        ], [
            'unidades' => 'unidades por caja',
            'tamano' => 'tamaño',
        ]);

        DB::transaction(function () use ($publicar) {
            $product = $this->product ?? new Product(['orden' => (Product::max('orden') ?? 0) + 1]);

            $product->fill([
                'nombre' => $this->nombre,
                'nombre_corto' => $product->exists ? $product->nombre_corto : Str::limit($this->nombre, 20, ''),
                'codigo' => $this->codigo,
                'categoria' => $this->categoria,
                'unidades_por_caja' => $this->unidades !== '' ? (int) $this->unidades : null,
                'tamano_cm' => $this->tamano !== '' ? (int) $this->tamano : null,
                'detalle' => collect([
                    $this->unidades !== '' ? 'Caja x '.$this->unidades.' unid.' : null,
                    $this->tamano !== '' ? $this->tamano.' cm' : null,
                ])->filter()->implode(' · ') ?: null,
                'descripcion' => $this->descripcion ?: null,
                'etiquetas' => $this->etiquetas,
                'visible_web' => $this->visibleWeb,
                'se_puede_pedir' => $this->sePuedePedir,
                'dias_produccion' => $this->dias,
                'actualizado_por' => auth()->user()->name,
            ]);

            if ($this->foto) {
                $product->imagen = 'storage/'.$this->foto->store('productos', 'public');
            } elseif ($this->quitarFoto) {
                $product->imagen = null;
            }

            $product->save();
            $this->product = $product;

            $cambiaron = collect($this->precios)->filter(
                fn (string $valor, string $nivel) => Formato::leerMonto($valor) !== (isset($this->preciosActuales[$nivel]) ? (float) $this->preciosActuales[$nivel] : null)
            );

            if ($cambiaron->isNotEmpty() || ($publicar && $this->borrador)) {
                $borrador = PriceList::borradorOCrear();

                foreach ($cambiaron as $nivel => $valor) {
                    $borrador->fijar($product, $nivel, Formato::leerMonto($valor));
                }

                if ($publicar && $borrador->cambiosContra(PriceList::vigente()) > 0) {
                    $borrador->publicar(auth()->user()->firma());
                }
            }
        });

        $this->redirectRoute('admin.precios', navigate: true);
    }
}; ?>

@php
    $letrasDias = [1 => 'L', 2 => 'M', 3 => 'X', 4 => 'J', 5 => 'V', 6 => 'S', 7 => 'D'];
    $imagen = $foto ? $foto->temporaryUrl() : ($quitarFoto ? null : $product?->imagen);
@endphp

<div class="pb-20">
    <a href="{{ route('admin.precios') }}" wire:navigate class="text-sm font-semibold text-tomate underline underline-offset-4">← Volver a la lista de precios</a>

    <x-admin.encabezado :titulo="$product?->nombre ?? 'Nuevo producto'">
        <x-slot:bajada>
            <p class="mt-2 text-sm text-tinta">
                @if ($product)
                    {{ $product->codigo }}, línea {{ $product->linea() }}.
                    @if (! $product->activo) <span class="font-semibold text-tomate">Pausado.</span> @endif
                    @if ($this->borrador) Cambios sin publicar. @endif
                @else
                    Completá los datos y los precios por caja.
                @endif
            </p>
        </x-slot:bajada>
        @if ($product)
            <x-slot:acciones>
                <button type="button" wire:click="alternarPausa" class="tienda-btn-borde !py-2.5">{{ $product->activo ? 'Pausar producto' : 'Reactivar producto' }}</button>
            </x-slot:acciones>
        @endif
    </x-admin.encabezado>

    <div class="grid gap-4 lg:grid-cols-2 xl:grid-cols-[16rem_1fr_1.15fr]">
        <div class="space-y-4">
            <section class="rounded-2xl border border-linea bg-white p-4">
                <div class="flex aspect-square items-center justify-center overflow-hidden rounded-xl bg-harina">
                    @if ($imagen)
                        <img src="{{ str_starts_with($imagen, 'http') ? $imagen : asset($imagen) }}" alt="" class="size-full object-cover">
                    @elseif ($product)
                        <x-tienda.pizza :variante="$product->ilustracion" class="w-3/4" />
                    @else
                        <span class="text-sm text-tinta">Sin foto</span>
                    @endif
                </div>
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <label class="tienda-btn-borde cursor-pointer !px-2 !py-2">
                        Cambiar foto
                        <input type="file" wire:model="foto" accept="image/*" class="sr-only">
                    </label>
                    <button type="button" wire:click="quitarImagen" class="tienda-btn-borde !px-2 !py-2">Quitar</button>
                </div>
                <p wire:loading wire:target="foto" class="mt-2 text-xs text-tinta">Subiendo foto…</p>
                @error('foto') <p class="mt-2 text-xs text-tomate">{{ $message }}</p> @enderror
            </section>

            <x-admin.tarjeta titulo="Disponibilidad">
                <div class="divide-y divide-linea text-sm">
                    <div class="flex items-center justify-between gap-3 pb-3">
                        <div>
                            <p class="font-bold">Visible en la web</p>
                            <p class="text-xs text-tinta">Aparece en el catálogo de clientes</p>
                        </div>
                        <x-admin.interruptor :activo="$visibleWeb" etiqueta="Visible en la web" wire:click="$toggle('visibleWeb')" />
                    </div>
                    <div class="flex items-center justify-between gap-3 py-3">
                        <div>
                            <p class="font-bold">Se puede pedir</p>
                            <p class="text-xs text-tinta">Si lo apagás, se ve pero sin botón de pedido</p>
                        </div>
                        <x-admin.interruptor :activo="$sePuedePedir" etiqueta="Se puede pedir" wire:click="$toggle('sePuedePedir')" />
                    </div>
                    <div class="pt-3">
                        <p class="mb-2 text-xs font-bold">Días que se produce</p>
                        <div class="flex gap-1.5">
                            @foreach ($letrasDias as $numero => $letra)
                                <button type="button" wire:click="alternarDia({{ $numero }})" aria-pressed="{{ in_array($numero, $this->dias) ? 'true' : 'false' }}" @class([
                                    'flex size-8 items-center justify-center rounded-md border text-xs font-bold',
                                    'border-carbon bg-carbon text-white' => in_array($numero, $this->dias),
                                    'border-linea bg-white text-tinta' => ! in_array($numero, $this->dias),
                                ])>{{ $letra }}</button>
                            @endforeach
                        </div>
                    </div>
                </div>
            </x-admin.tarjeta>
        </div>

        <x-admin.tarjeta titulo="Datos del producto" class="self-start">
            <div class="space-y-4">
                <label class="block">
                    <span class="tienda-label">Nombre</span>
                    <input type="text" wire:model="nombre" class="tienda-input">
                    @error('nombre') <span class="mt-1 block text-xs text-tomate">{{ $message }}</span> @enderror
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="block">
                        <span class="tienda-label">Código</span>
                        <input type="text" wire:model="codigo" class="tienda-input uppercase" placeholder="PZ-06">
                        @error('codigo') <span class="mt-1 block text-xs text-tomate">{{ $message }}</span> @enderror
                    </label>
                    <label class="block">
                        <span class="tienda-label">Línea</span>
                        <select wire:model="categoria" class="tienda-input">
                            @foreach (config('tienda.categorias') as $clave => $nombreLinea)
                                <option value="{{ $clave }}">{{ $nombreLinea }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <label class="block">
                        <span class="tienda-label">Unidades por caja</span>
                        <input type="number" min="1" wire:model="unidades" class="tienda-input" placeholder="Ej.: 12">
                        @error('unidades') <span class="mt-1 block text-xs text-tomate">{{ $message }}</span> @enderror
                    </label>
                    <label class="block">
                        <span class="tienda-label">Tamaño</span>
                        <span class="flex items-center rounded-md border border-linea bg-white pr-3 focus-within:border-carbon">
                            <input type="number" min="1" wire:model="tamano" class="w-full min-w-0 bg-transparent px-3 py-2.5 text-sm focus:outline-none" placeholder="Ej.: 30">
                            <span class="text-sm text-tinta">cm</span>
                        </span>
                        @error('tamano') <span class="mt-1 block text-xs text-tomate">{{ $message }}</span> @enderror
                    </label>
                </div>
                <label class="block">
                    <span class="tienda-label">Descripción para el cliente</span>
                    <textarea wire:model="descripcion" rows="3" class="tienda-input"></textarea>
                </label>
                <div>
                    <span class="tienda-label">Etiquetas</span>
                    <div class="flex flex-wrap items-center gap-1.5">
                        @foreach ($etiquetas as $i => $etiqueta)
                            <span wire:key="etiqueta-{{ $i }}" @class(['inline-flex items-center gap-1 rounded-full py-1 pl-2.5 pr-1.5 text-xs font-semibold', 'bg-queso text-carbon' => $i === 0, 'bg-arena text-carbon' => $i > 0])>
                                {{ $etiqueta }}
                                <button type="button" wire:click="quitarEtiqueta({{ $i }})" class="rounded-full px-1 hover:bg-carbon/10" aria-label="Quitar etiqueta {{ $etiqueta }}">×</button>
                            </span>
                        @endforeach
                        @if ($agregandoEtiqueta)
                            <input type="text" wire:model="nuevaEtiqueta" wire:keydown.enter.prevent="agregarEtiqueta" wire:blur="agregarEtiqueta" class="rounded-full border border-linea px-2.5 py-1 text-xs focus:border-carbon focus:outline-none" placeholder="Nueva etiqueta" autofocus>
                        @else
                            <button type="button" wire:click="$set('agregandoEtiqueta', true)" class="text-xs font-semibold text-tomate">+ Agregar</button>
                        @endif
                    </div>
                </div>
            </div>
        </x-admin.tarjeta>

        <div class="space-y-4 lg:col-span-2 xl:col-span-1">
            <section class="overflow-hidden rounded-2xl border border-linea bg-white">
                <h2 class="px-5 pt-5 font-display text-2xl uppercase">Precios por caja</h2>
                <table class="mt-3 w-full text-sm">
                    <thead class="border-b-2 border-carbon text-left text-[0.65rem] font-bold uppercase tracking-[0.12em] text-tinta">
                        <tr>
                            <th class="px-5 py-2.5">Nivel</th>
                            <th class="px-2 py-2.5">Precio nuevo</th>
                            <th class="px-2 py-2.5">Vigente</th>
                            <th class="px-5 py-2.5 text-right">Cambio</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-linea">
                        @foreach (Niveles::claves() as $i => $nivel)
                            @php
                                $nuevo = Formato::leerMonto($precios[$nivel] ?? '');
                                $vigente = $this->preciosVigentes[$nivel] ?? null;
                                $cambio = $nuevo !== null && $vigente !== null && (float) $vigente !== $nuevo ? Formato::variacion($vigente, $nuevo) : null;
                                $descuento = $i > 0 && $nuevo && ($inicial = Formato::leerMonto($precios[Niveles::claves()[0]] ?? '')) ? round((1 - $nuevo / $inicial) * 100) : null;
                            @endphp
                            <tr wire:key="precio-{{ $nivel }}">
                                <td class="px-5 py-3">
                                    <x-admin.badge :clases="Niveles::clases($nivel)">{{ Niveles::nombre($nivel) }}</x-admin.badge>
                                    <p class="mt-1 text-xs text-tinta">{{ $i === 0 ? 'base' : ($descuento === null ? '—' : '− '.$descuento.' %') }}</p>
                                </td>
                                <td class="px-2 py-3">
                                    <label @class(['flex w-32 items-center gap-1 rounded-md border px-2.5 py-1.5 font-bold focus-within:ring-2 focus-within:ring-carbon/10', 'border-carbon bg-queso-claro' => $cambio, 'border-linea bg-white' => ! $cambio])>
                                        $<input type="text" inputmode="decimal" wire:model.live.debounce.400ms="precios.{{ $nivel }}" placeholder="0" class="w-full min-w-0 bg-transparent focus:outline-none" aria-label="Precio {{ Niveles::nombre($nivel) }}">
                                    </label>
                                    <p class="mt-1 text-xs text-tinta">
                                        @if ($nuevo && $unidades)
                                            {{ Formato::dinero(round($nuevo / (int) $unidades, 2)) }} por unidad
                                        @endif
                                    </p>
                                    @error('precios.'.$nivel) <p class="mt-1 text-xs text-tomate">{{ $message }}</p> @enderror
                                </td>
                                <td class="px-2 py-3 text-tinta">{{ Formato::dinero($vigente) }}</td>
                                <td class="px-5 py-3 text-right">
                                    @if ($cambio)
                                        <x-admin.badge clases="bg-queso-claro text-carbon">{{ $cambio }}</x-admin.badge>
                                    @else
                                        <span class="text-tinta">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if ($this->borrador)
                    <div class="m-4 rounded-r-lg border-l-4 border-queso bg-queso-claro px-4 py-2.5 text-sm">
                        <p class="font-bold">Rige desde el {{ $this->borrador->vigenteDesdeTexto() }}</p>
                        <p class="text-tinta">Hasta esa fecha se cobra el precio vigente.</p>
                    </div>
                @else
                    <p class="m-4 rounded-lg bg-harina px-4 py-2.5 text-xs text-tinta">Si cambiás un precio se guarda en el borrador de la próxima lista.</p>
                @endif
            </section>

            @if ($product)
                <section class="overflow-hidden rounded-2xl border border-linea bg-white">
                    <div class="flex items-center justify-between px-5 pt-5">
                        <h2 class="font-display text-2xl uppercase">Historial</h2>
                        <span class="text-sm text-tinta">Precio {{ Niveles::nombre(Niveles::claves()[0]) }}</span>
                    </div>
                    <table class="mt-3 w-full text-sm">
                        <tbody class="divide-y divide-linea border-t border-linea">
                            @forelse ($this->historial as $fila)
                                <tr wire:key="historial-{{ $fila['lista']->id }}">
                                    <td class="px-5 py-2.5 font-bold">{{ $fila['lista']->nombre() }}</td>
                                    <td class="px-2 py-2.5">{{ mb_strtolower(Formato::fecha($fila['lista']->vigente_desde, 'j M Y')) }}</td>
                                    <td class="px-2 py-2.5">{{ Formato::dinero($fila['precio']) }}</td>
                                    <td class="px-5 py-2.5 text-right">{{ $fila['cambio'] ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td class="px-5 py-4 text-tinta">Todavía no hay listas publicadas.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </section>
            @endif
        </div>
    </div>

    {{-- Barra de acciones --}}
    <div class="fixed inset-x-0 bottom-0 z-10 flex flex-wrap items-center justify-between gap-3 border-t border-linea bg-white px-4 py-3 sm:px-8 lg:left-60">
        <p class="text-sm text-tinta">
            @if ($product?->actualizado_por)
                Último cambio: {{ $product->actualizado_por }}, {{ $product->updated_at->isToday() ? 'hoy' : mb_strtolower(Formato::fecha($product->updated_at)) }} {{ $product->updated_at->format('H:i') }}
            @endif
        </p>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.precios') }}" wire:navigate class="tienda-btn-borde !py-2.5">Cancelar</a>
            <button type="button" wire:click="guardar" class="tienda-btn-oscuro !py-2.5">Guardar en borrador</button>
            <button type="button" wire:click="guardar(true)" wire:confirm="Se guarda el producto y se publican todos los precios del borrador. ¿Seguir?" class="tienda-btn-primario !py-2.5">Guardar y publicar</button>
        </div>
    </div>
</div>
