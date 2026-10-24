<?php

use App\Models\Order;
use App\Models\PriceList;
use App\Models\Product;
use App\Support\Formato;
use App\Support\Niveles;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::tienda')] #[Title('Catálogo mayorista')] class extends Component {
    #[Url]
    public string $categoria = '';

    #[Url(as: 'q')]
    public string $buscar = '';

    /** @var array<string, int> código de producto => cajas */
    public array $carrito = [];

    public function mount(): void
    {
        $this->carrito = session('carrito', []);
    }

    /**
     * Catálogo activo, indexado por código.
     *
     * @return Collection<string, array<string, mixed>>
     */
    #[Computed]
    public function catalogo(): Collection
    {
        $precios = PriceList::mapaVigente();

        return Product::activos()->get()->mapWithKeys(fn (Product $producto) => [$producto->codigo => [
            'codigo' => $producto->codigo,
            'nombre' => $producto->nombre,
            'categoria' => $producto->categoria,
            'etiqueta' => $producto->etiqueta(),
            'detalle' => $producto->detalle,
            'ilustracion' => $producto->ilustracion,
            'imagen' => $producto->imagen,
            'precios' => $precios[$producto->id] ?? [],
            'con_precio' => $conPrecio = Niveles::completos($precios[$producto->id] ?? []),
            'pedible' => $producto->se_puede_pedir && $conPrecio,
        ]]);
    }

    #[Computed]
    public function productos(): array
    {
        $buscar = mb_strtolower(trim($this->buscar));

        return $this->catalogo
            ->when($this->categoria !== '', fn ($productos) => $productos->where('categoria', $this->categoria))
            ->when($buscar !== '', fn ($productos) => $productos->filter(
                fn ($producto) => str_contains(mb_strtolower($producto['nombre'].' '.$producto['codigo']), $buscar)
            ))
            ->values()
            ->all();
    }

    #[Computed]
    public function cajas(): int
    {
        return array_sum($this->carrito);
    }

    #[Computed]
    public function nivel(): array
    {
        $clave = Niveles::paraCajas($this->cajas, auth()->user()?->customer?->nivel);

        return [...Niveles::todos()[$clave], 'clave' => $clave];
    }

    /**
     * Totales del carrito sin envío, que se define al confirmar.
     *
     * @return array{subtotal: float, descuento: float, envio: float, iva: float, total: float}|null
     */
    #[Computed]
    public function totales(): ?array
    {
        return Order::calcularTotales(
            collect($this->carrito)
                ->filter(fn ($cantidad, $codigo) => $this->catalogo->has($codigo))
                ->map(fn ($cantidad, $codigo) => ['precios' => $this->catalogo[$codigo]['precios'], 'cajas' => $cantidad]),
            $this->nivel['clave'],
            reparto: false,
        );
    }

    public function sumar(string $codigo): void
    {
        if (! ($this->producto($codigo)['pedible'] ?? false)) {
            return;
        }

        $this->carrito[$codigo] = ($this->carrito[$codigo] ?? 0) + 1;
        $this->guardar();
    }

    public function restar(string $codigo): void
    {
        if (! isset($this->carrito[$codigo])) {
            return;
        }

        $this->carrito[$codigo]--;

        if ($this->carrito[$codigo] <= 0) {
            unset($this->carrito[$codigo]);
        }

        $this->guardar();
    }

    public function quitar(string $codigo): void
    {
        unset($this->carrito[$codigo]);
        $this->guardar();
    }

    public function continuar(): void
    {
        if ($this->cajas < config('tienda.pedido_minimo')) {
            return;
        }

        $this->redirectRoute('pedido', navigate: true);
    }

    public function producto(string $codigo): ?array
    {
        return $this->catalogo->get($codigo);
    }

    protected function guardar(): void
    {
        session(['carrito' => $this->carrito]);
    }
}; ?>

<div class="min-h-screen bg-white">
    {{-- Encabezado --}}
    <header class="border-b border-linea bg-white">
        <div class="mx-auto flex max-w-7xl items-center gap-4 px-4 py-3 sm:gap-8 sm:px-6 lg:px-8">
            <x-tienda.logo class="shrink-0" />

            <label class="relative hidden max-w-sm flex-1 sm:block">
                <span class="sr-only">Buscar productos</span>
                <svg class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-tinta" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" /></svg>
                <input wire:model.live.debounce.300ms="buscar" type="search" class="tienda-input !pl-9" placeholder="Buscar por variedad o código">
            </label>

            <div class="ml-auto flex items-center gap-4 text-sm">
                @auth
                    <span class="hidden text-tinta md:inline">Hola, <a href="{{ route('dashboard') }}" class="font-semibold text-carbon">{{ auth()->user()->name }}</a></span>
                @else
                    <a href="{{ route('login') }}" class="font-semibold hover:text-tomate">Ingresar</a>
                @endauth
                <a href="#tu-pedido" class="tienda-btn-oscuro !px-4 !py-2.5">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 7h12l-1 13H7L6 7Z" /><path d="M9 7a3 3 0 0 1 6 0" /></svg>
                    {{ $this->cajas }} {{ $this->cajas === 1 ? 'caja' : 'cajas' }}
                </a>
            </div>
        </div>
    </header>

    <div class="mx-auto grid max-w-7xl gap-8 px-4 py-8 sm:px-6 lg:grid-cols-[1fr_20rem] lg:px-8">
        <main>
            <nav class="text-xs text-tinta"><a href="{{ route('home') }}" class="underline">Inicio</a> / Catálogo</nav>
            <h1 class="mt-1 font-display text-5xl uppercase">Catálogo mayorista</h1>
            <p class="mt-2 text-sm text-tinta">Precios por caja, IVA no incluido. Mezclá variedades: el descuento se calcula sobre el total de cajas.</p>

            {{-- Buscador en mobile --}}
            <input wire:model.live.debounce.300ms="buscar" type="search" class="tienda-input mt-4 sm:hidden" placeholder="Buscar por variedad o código">

            {{-- Filtros: solo si hay más de una categoría con productos --}}
            @php($categorias = collect(config('tienda.categorias'))->only($this->catalogo->pluck('categoria')->unique()->all()))
            <div @class(['mt-5 flex flex-wrap gap-2', 'hidden' => $categorias->count() < 2])>
                @foreach (['' => 'Todos'] + $categorias->all() as $clave => $nombre)
                    <button
                        type="button"
                        wire:click="$set('categoria', '{{ $clave }}')"
                        @class([
                            'rounded-full border-[1.5px] px-4 py-2 text-sm font-semibold transition',
                            'border-carbon bg-carbon text-white' => $categoria === $clave,
                            'border-linea bg-white hover:border-carbon' => $categoria !== $clave,
                        ])
                    >{{ $nombre }}</button>
                @endforeach
            </div>

            <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @forelse ($this->productos as $producto)
                    @php($cantidad = $carrito[$producto['codigo']] ?? 0)
                    <article wire:key="producto-{{ $producto['codigo'] }}" class="flex flex-col overflow-hidden rounded-xl border border-linea bg-white">
                        <div class="relative flex aspect-[5/7] items-center justify-center overflow-hidden bg-harina">
                            @if ($producto['imagen'])
                                <img src="{{ asset($producto['imagen']) }}" alt="{{ $producto['nombre'] }}" loading="lazy" class="size-full object-cover">
                            @else
                                <x-tienda.pizza :variante="$producto['ilustracion']" class="{{ $producto['ilustracion'] === 'bollos' ? 'w-44' : 'w-28' }}" />
                            @endif
                            <span class="absolute left-3 top-3 rounded-full bg-white px-2.5 py-1 text-[0.6rem] font-bold uppercase tracking-wider">{{ $producto['etiqueta'] }}</span>
                        </div>
                        <div class="flex flex-1 flex-col p-4">
                            <p class="text-[0.65rem] font-semibold text-tinta">{{ $producto['codigo'] }}</p>
                            <h2 class="mt-0.5 font-bold leading-snug">{{ $producto['nombre'] }}</h2>
                            <p class="mt-1 text-xs text-tinta">{{ $producto['detalle'] }}</p>
                            @if ($producto['con_precio'])
                                <p class="mt-3 mb-4"><span class="font-display text-3xl">{{ Formato::dinero($producto['precios'][$this->nivel['clave']]) }}</span> <span class="text-xs text-tinta">/ caja</span></p>
                            @else
                                <p class="mt-3 mb-4 text-sm font-semibold text-tinta">Precio a consultar</p>
                            @endif

                            <div class="mt-auto">
                                @if (! $producto['con_precio'])
                                    <button type="button" disabled class="tienda-btn-borde w-full cursor-not-allowed !py-2.5 opacity-60">Todavía no se puede pedir</button>
                                @elseif (! $producto['pedible'])
                                    <button type="button" disabled class="tienda-btn-borde w-full cursor-not-allowed !py-2.5 opacity-60">Sin stock para pedir</button>
                                @elseif ($cantidad > 0)
                                    <div class="flex items-center overflow-hidden rounded-md border-[1.5px] border-carbon">
                                        <button type="button" wire:click="restar('{{ $producto['codigo'] }}')" class="px-4 py-2.5 text-lg font-bold hover:bg-harina" aria-label="Quitar una caja">−</button>
                                        <span class="flex-1 text-center text-sm font-semibold">{{ $cantidad }} {{ $cantidad === 1 ? 'caja' : 'cajas' }}</span>
                                        <button type="button" wire:click="sumar('{{ $producto['codigo'] }}')" class="bg-carbon px-4 py-2.5 text-lg font-bold text-white hover:bg-carbon-suave" aria-label="Agregar una caja">+</button>
                                    </div>
                                @else
                                    <button type="button" wire:click="sumar('{{ $producto['codigo'] }}')" class="tienda-btn-primario w-full !py-2.5">Agregar caja</button>
                                @endif
                            </div>
                        </div>
                    </article>
                @empty
                    <p class="col-span-full rounded-xl bg-harina p-8 text-center text-sm text-tinta">No encontramos productos para tu búsqueda.</p>
                @endforelse
            </div>
        </main>

        {{-- Resumen del pedido --}}
        <aside id="tu-pedido" class="lg:sticky lg:top-6 lg:self-start">
            <div class="rounded-2xl border border-linea bg-white p-5">
                <h2 class="font-display text-3xl uppercase">Tu pedido</h2>

                @php($distribuidor = config('tienda.niveles.distribuidor.desde'))
                <div class="mt-4 rounded-lg bg-harina p-3">
                    <div class="flex justify-between text-xs">
                        <span class="font-bold">Nivel: {{ $this->nivel['nombre'] }}</span>
                        <span class="text-tinta">{{ $this->cajas }} cajas</span>
                    </div>
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-linea">
                        <div class="h-full rounded-full bg-tomate transition-all" style="width: {{ min(100, $this->cajas / $distribuidor * 100) }}%"></div>
                    </div>
                    <p class="mt-2 text-xs text-tinta">
                        @if ($this->cajas < $distribuidor)
                            Sumá {{ $distribuidor - $this->cajas }} cajas más para precio Distribuidor.
                        @else
                            Tenés precio Distribuidor.
                        @endif
                    </p>
                </div>

                <ul class="mt-2 divide-y divide-linea">
                    @forelse ($carrito as $codigo => $cantidad)
                        @php($producto = $this->producto($codigo))
                        @continue($producto === null)
                        <li wire:key="carrito-{{ $codigo }}" class="flex items-start justify-between gap-3 py-3">
                            <div>
                                <p class="text-sm font-semibold">{{ $producto['nombre'] }}</p>
                                <p class="text-xs text-tinta">{{ $cantidad }} {{ $cantidad === 1 ? 'caja' : 'cajas' }} × {{ Formato::dinero($producto['precios'][$this->nivel['clave']] ?? null) }}</p>
                            </div>
                            <button type="button" wire:click="quitar('{{ $codigo }}')" class="p-1 text-tinta hover:text-tomate" aria-label="Quitar {{ $producto['nombre'] }}">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" /></svg>
                            </button>
                        </li>
                    @empty
                        <li class="py-6 text-center text-sm text-tinta">Todavía no agregaste cajas.</li>
                    @endforelse
                </ul>

                <dl class="space-y-1.5 border-t border-linea pt-3 text-sm">
                    <div class="flex justify-between"><dt>Subtotal de lista</dt><dd>{{ Formato::dinero($this->totales['subtotal'] ?? null) }}</dd></div>
                    <div class="flex justify-between font-semibold text-albahaca"><dt>Descuento por volumen</dt><dd>− {{ Formato::dinero($this->totales['descuento'] ?? null) }}</dd></div>
                    <div class="flex justify-between"><dt>IVA ({{ config('tienda.iva') * 100 }} %)</dt><dd>{{ Formato::dinero($this->totales['iva'] ?? null) }}</dd></div>
                    <div class="flex justify-between"><dt>Envío</dt><dd>{{ config('tienda.envio') ? 'Se calcula al confirmar' : 'Sin cargo' }}</dd></div>
                </dl>
                <div class="mt-3 flex items-end justify-between border-t-2 border-carbon pt-3">
                    <span class="text-sm font-bold">Total</span>
                    <span class="font-display text-3xl">{{ Formato::dinero($this->totales['total'] ?? null) }}</span>
                </div>

                <button
                    type="button"
                    wire:click="continuar"
                    @disabled($this->cajas < config('tienda.pedido_minimo'))
                    class="tienda-btn-primario mt-4 w-full disabled:cursor-not-allowed disabled:opacity-50"
                >Continuar con el pedido</button>
                <p class="mt-3 text-center text-xs text-tinta">Pedido mínimo: {{ config('tienda.pedido_minimo') }} cajas</p>
                @guest
                    <p class="mt-2 text-center text-xs text-tinta">Para confirmar el pedido vas a tener que <a href="{{ route('login') }}" class="font-semibold text-tomate underline">ingresar</a> o <a href="{{ route('register') }}" class="font-semibold text-tomate underline">crear tu cuenta</a>. Tu carrito se guarda.</p>
                @endguest
            </div>
        </aside>
    </div>
</div>
