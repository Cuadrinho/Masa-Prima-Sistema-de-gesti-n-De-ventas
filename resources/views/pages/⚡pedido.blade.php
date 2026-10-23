<?php

use App\Enums\EstadoPedido;
use App\Enums\Franja;
use App\Enums\MetodoPago;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Rules\Cuit;
use App\Support\Formato;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::tienda')] #[Title('Confirmá tu pedido')] class extends Component {
    /** @var array<string, int> código de producto => cajas */
    public array $carrito = [];

    public string $razonSocial = '';

    public string $cuit = '';

    public string $condicionIva = 'A';

    public string $entrega = 'reparto';

    public string $direccion = '';

    public string $dia = '';

    public string $franja = 'manana';

    public string $indicaciones = '';

    public string $pago = 'transferencia';

    /** Número del pedido una vez confirmado. */
    public ?int $confirmado = null;

    public function mount(): void
    {
        $this->carrito = session('carrito', []);
        $this->dia = array_key_first($this->dias);

        if ($this->cajas < config('tienda.pedido_minimo')) {
            $this->redirectRoute('catalogo', navigate: true);

            return;
        }

        // Datos del comercio de la cuenta y la entrega del último pedido.
        if ($cliente = $this->cliente) {
            $this->razonSocial = $cliente->razon_social;
            $this->cuit = $cliente->cuitFormateado();
            $this->condicionIva = $cliente->condicion_iva;

            if ($ultimo = $cliente->orders()->latest('id')->first()) {
                $this->entrega = $ultimo->entrega_tipo;
                $this->direccion = $ultimo->direccion ?? '';
                $this->franja = $ultimo->franja->value;
            }
        }
    }

    /**
     * Comercio asociado a la cuenta, o null si es su primer pedido.
     */
    #[Computed]
    public function cliente(): ?Customer
    {
        return auth()->user()->customer;
    }

    /**
     * @return Collection<string, Product>
     */
    #[Computed]
    public function productos(): Collection
    {
        return Product::activos()->whereIn('codigo', array_keys($this->carrito))->get()->keyBy('codigo');
    }

    #[Computed]
    public function items(): array
    {
        return collect($this->carrito)
            ->filter(fn ($cantidad, $codigo) => $this->productos->has($codigo))
            ->map(fn ($cantidad, $codigo) => ['producto' => $this->productos[$codigo], 'cantidad' => $cantidad])
            ->values()
            ->all();
    }

    #[Computed]
    public function cajas(): int
    {
        return collect($this->items)->sum('cantidad');
    }

    #[Computed]
    public function nivel(): string
    {
        return Order::nivelPara($this->cajas);
    }

    /**
     * Próximos días hábiles de reparto (lunes a sábado).
     *
     * @return array<string, string> fecha => etiqueta
     */
    #[Computed]
    public function dias(): array
    {
        $dias = [];
        $fecha = Carbon::tomorrow();

        while (count($dias) < 6) {
            if (! $fecha->isSunday()) {
                $dias[$fecha->toDateString()] = Formato::fecha($fecha, 'l j \d\e F');
            }

            $fecha = $fecha->addDay();
        }

        return $dias;
    }

    public function confirmar(): void
    {
        if ($this->cajas < config('tienda.pedido_minimo')) {
            $this->redirectRoute('catalogo', navigate: true);

            return;
        }

        $cliente = $this->cliente;

        $this->validate([
            ...($cliente ? [] : [
                'razonSocial' => ['required', 'string', 'max:255'],
                'cuit' => ['required', new Cuit, function (string $attribute, mixed $value, Closure $fail) {
                    if (Customer::where('cuit', Cuit::normalizar($value))->exists()) {
                        $fail('Este CUIT ya está registrado en otra cuenta. Escribinos para vincularlo a la tuya.');
                    }
                }],
                'condicionIva' => ['required', Rule::in(['A', 'B'])],
            ]),
            'entrega' => ['required', Rule::in(['reparto', 'retiro'])],
            'direccion' => ['required_if:entrega,reparto', 'nullable', 'string', 'max:255'],
            'dia' => ['required', Rule::in(array_keys($this->dias))],
            'franja' => ['required', Rule::enum(Franja::class)],
            'indicaciones' => ['nullable', 'string', 'max:500'],
            'pago' => ['required', Rule::enum(MetodoPago::class)],
        ], attributes: [
            'razonSocial' => 'razón social',
            'direccion' => 'dirección de entrega',
            'dia' => 'día',
        ]);

        if ($this->pago === MetodoPago::CuentaCorriente->value && ! $cliente?->tieneCuentaCorriente()) {
            $this->addError('pago', 'La cuenta corriente es solo para clientes aprobados. Elegí otra forma de pago.');

            return;
        }

        $order = DB::transaction(function () use ($cliente) {
            // Primer pedido de la cuenta: se crea el comercio y queda asociado al usuario.
            $cliente ??= auth()->user()->customer()->create([
                'razon_social' => $this->razonSocial,
                'cuit' => Cuit::normalizar($this->cuit),
                'condicion_iva' => $this->condicionIva,
                'email' => auth()->user()->email,
            ]);

            $order = $cliente->orders()->create([
                'estado' => EstadoPedido::Solicitud,
                'entrega_tipo' => $this->entrega,
                'entrega_fecha' => $this->dia,
                'franja' => $this->franja,
                'direccion' => $this->entrega === 'reparto' ? $this->direccion : null,
                'indicaciones' => $this->indicaciones ?: null,
                'metodo_pago' => $this->pago,
                'nivel' => $this->nivel,
            ]);

            foreach ($this->items as $item) {
                $order->items()->create([
                    'product_id' => $item['producto']->id,
                    'cajas' => $item['cantidad'],
                    'precio_unitario' => $item['producto']->precio,
                ]);
            }

            $order->registrar($cliente->razon_social, "Realizó el pedido online · {$this->cajas} cajas · Nivel {$this->nivel}.");

            return $order;
        });

        session()->forget('carrito');
        $this->confirmado = $order->id;
    }
}; ?>

<div class="min-h-screen bg-harina">
    {{-- Encabezado con pasos --}}
    <header class="border-b border-linea bg-white">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
            <x-tienda.logo :bajada="false" />

            <ol class="flex items-center gap-4 text-xs font-semibold sm:gap-6 sm:text-sm">
                <li>
                    <a href="{{ route('catalogo') }}" wire:navigate class="flex items-center gap-2 text-albahaca underline underline-offset-2">
                        <span class="flex size-6 items-center justify-center rounded-full bg-albahaca text-white">
                            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5L20 7" /></svg>
                        </span>
                        <span class="hidden sm:inline">Productos</span>
                    </a>
                </li>
                <li @class(['flex items-center gap-2', 'text-albahaca' => $confirmado]) @if (! $confirmado) aria-current="step" @endif>
                    <span @class(['flex size-6 items-center justify-center rounded-full text-white', 'bg-carbon' => ! $confirmado, 'bg-albahaca' => $confirmado])>
                        @if ($confirmado)
                            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5L20 7" /></svg>
                        @else
                            2
                        @endif
                    </span>
                    <span class="hidden sm:inline">Datos y entrega</span>
                </li>
                <li @class(['flex items-center gap-2', 'text-tinta' => ! $confirmado]) @if ($confirmado) aria-current="step" @endif>
                    <span @class(['flex size-6 items-center justify-center rounded-full', 'border-[1.5px] border-linea' => ! $confirmado, 'bg-carbon text-white' => $confirmado])>3</span>
                    <span class="hidden sm:inline">Confirmación</span>
                </li>
            </ol>
        </div>
    </header>

    @if ($confirmado)
        {{-- Paso 3: confirmación --}}
        <div class="mx-auto max-w-2xl px-4 py-16 text-center sm:px-6">
            <span class="mx-auto flex size-16 items-center justify-center rounded-full bg-albahaca text-white">
                <svg class="size-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5L20 7" /></svg>
            </span>
            <p class="mt-6 text-xs font-bold uppercase tracking-[0.15em] text-tomate">Pedido #{{ $confirmado }}</p>
            <h1 class="mt-2 font-display text-5xl uppercase leading-none sm:text-6xl">¡Recibimos tu pedido!</h1>
            <p class="mx-auto mt-5 max-w-md leading-relaxed text-tinta">
                Lo revisamos y te confirmamos por email y WhatsApp cuando entre a cocina.
                @if ($pago === 'transferencia')
                    Te vamos a enviar el CBU / alias para la transferencia.
                @elseif ($pago === 'mercadopago')
                    Te vamos a enviar el link de pago de Mercado Pago.
                @endif
            </p>
            <a href="{{ route('catalogo') }}" wire:navigate class="tienda-btn-primario mt-8">Volver al catálogo</a>
        </div>
    @else
        <form wire:submit="confirmar" class="mx-auto grid max-w-6xl gap-6 px-4 py-8 sm:px-6 lg:grid-cols-[1fr_20rem] lg:px-8">
            <main class="space-y-5">
                <h1 class="font-display text-5xl uppercase">Confirmá tu pedido</h1>

                {{-- Facturación --}}
                <section class="rounded-2xl border border-linea bg-white p-5">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <h2 class="font-bold">Datos de facturación</h2>
                        <p class="text-xs text-tinta">Cuenta: {{ auth()->user()->email }}</p>
                    </div>

                    @if ($this->cliente)
                        <dl class="mt-4 grid gap-4 rounded-xl bg-harina p-4 text-sm sm:grid-cols-3">
                            <div><dt class="tienda-label">Razón social</dt><dd class="font-semibold">{{ $this->cliente->razon_social }}</dd></div>
                            <div><dt class="tienda-label">CUIT</dt><dd class="font-semibold">{{ $this->cliente->cuitFormateado() }}</dd></div>
                            <div><dt class="tienda-label">Comprobante</dt><dd class="font-semibold">{{ $this->cliente->comprobante() }}</dd></div>
                        </dl>
                        <p class="mt-2 text-xs text-tinta">Para cambiar estos datos, escribinos por WhatsApp.</p>
                    @else
                    <p class="mt-1 text-xs text-tinta">Es tu primer pedido: estos datos quedan guardados en tu cuenta para los próximos.</p>
                    <div class="mt-4 grid gap-4 sm:grid-cols-3">
                        <div>
                            <label for="razon_social" class="tienda-label">Razón social</label>
                            <input id="razon_social" wire:model="razonSocial" class="tienda-input" placeholder="Pizzería Don Carlos S.R.L.">
                            @error('razonSocial') <p class="mt-1 text-xs text-tomate">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="cuit" class="tienda-label">CUIT</label>
                            <input id="cuit" wire:model="cuit" class="tienda-input" placeholder="30-12345678-9" inputmode="numeric">
                            @error('cuit') <p class="mt-1 text-xs text-tomate">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="comprobante" class="tienda-label">Comprobante</label>
                            <select id="comprobante" wire:model="condicionIva" class="tienda-input">
                                <option value="A">Factura A — Responsable inscripto</option>
                                <option value="B">Factura B — Monotributo / Exento</option>
                            </select>
                        </div>
                    </div>
                    @endif
                </section>

                {{-- Entrega --}}
                <section class="rounded-2xl border border-linea bg-white p-5">
                    <h2 class="font-bold">Entrega</h2>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        @foreach ([
                            'reparto' => ['Reparto a domicilio', 'Camión refrigerado · costo [A CALCULAR]'],
                            'retiro' => ['Retiro por fábrica', '[DIRECCIÓN] · sin cargo'],
                        ] as $valor => [$titulo, $detalle])
                            <label @class([
                                'flex cursor-pointer items-start gap-3 rounded-xl p-4 transition',
                                'border-2 border-carbon' => $entrega === $valor,
                                'border border-linea hover:border-tinta' => $entrega !== $valor,
                            ])>
                                <input type="radio" wire:model.live="entrega" value="{{ $valor }}" class="mt-0.5 size-4 accent-tomate">
                                <span>
                                    <span class="block text-sm font-bold">{{ $titulo }}</span>
                                    <span class="block text-xs text-tinta">{{ $detalle }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        @if ($entrega === 'reparto')
                            <div class="sm:col-span-2">
                                <label for="direccion" class="tienda-label">Dirección de entrega</label>
                                <input id="direccion" wire:model="direccion" class="tienda-input" placeholder="Av. [CALLE] 1234">
                                @error('direccion') <p class="mt-1 text-xs text-tomate">{{ $message }}</p> @enderror
                            </div>
                        @endif
                        <div>
                            <label for="dia" class="tienda-label">{{ $entrega === 'reparto' ? 'Día de entrega' : 'Día de retiro' }}</label>
                            <select id="dia" wire:model="dia" class="tienda-input">
                                @foreach ($this->dias as $fecha => $etiqueta)
                                    <option value="{{ $fecha }}">{{ $etiqueta }}</option>
                                @endforeach
                            </select>
                            @error('dia') <p class="mt-1 text-xs text-tomate">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="franja" class="tienda-label">Franja horaria</label>
                            <select id="franja" wire:model="franja" class="tienda-input">
                                @foreach (Franja::cases() as $opcion)
                                    <option value="{{ $opcion->value }}">{{ $opcion->etiqueta() }} ({{ $opcion->horario() }})</option>
                                @endforeach
                            </select>
                        </div>
                        @if ($entrega === 'reparto')
                            <div class="sm:col-span-2">
                                <label for="indicaciones" class="tienda-label">Indicaciones para el repartidor</label>
                                <textarea id="indicaciones" wire:model="indicaciones" rows="3" class="tienda-input" placeholder="Ej.: entrada por portón lateral, preguntar por Carlos"></textarea>
                            </div>
                        @endif
                    </div>
                </section>

                {{-- Pago --}}
                <section class="rounded-2xl border border-linea bg-white p-5">
                    <h2 class="font-bold">Forma de pago</h2>
                    <div class="mt-4 grid gap-3 sm:grid-cols-3">
                        @foreach ([
                            'transferencia' => ['Transferencia', 'CBU / alias al confirmar'],
                            'mercadopago' => ['Mercado Pago', 'Link de pago'],
                            'cuenta_corriente' => ['Cuenta corriente', 'Clientes aprobados · [X] días'],
                        ] as $valor => [$titulo, $detalle])
                            <label @class([
                                'flex cursor-pointer items-start gap-3 rounded-xl p-4 transition',
                                'border-2 border-carbon' => $pago === $valor,
                                'border border-linea hover:border-tinta' => $pago !== $valor,
                            ])>
                                <input type="radio" wire:model.live="pago" value="{{ $valor }}" class="mt-0.5 size-4 accent-tomate">
                                <span>
                                    <span class="block text-sm font-bold">{{ $titulo }}</span>
                                    <span class="block text-xs text-tinta">{{ $detalle }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('pago') <p class="mt-2 text-xs text-tomate">{{ $message }}</p> @enderror
                </section>
            </main>

            {{-- Resumen --}}
            <aside class="lg:sticky lg:top-6 lg:self-start">
                <div class="rounded-2xl border border-linea bg-white p-5">
                    <div class="flex items-center justify-between">
                        <h2 class="font-display text-3xl uppercase">Resumen</h2>
                        <a href="{{ route('catalogo') }}" wire:navigate class="text-sm font-semibold text-tomate underline underline-offset-2">Editar</a>
                    </div>

                    <ul class="mt-3 divide-y divide-linea text-sm">
                        @foreach ($this->items as $item)
                            <li class="flex justify-between gap-3 py-2.5">
                                <span>{{ $item['cantidad'] }} × {{ $item['producto']->nombre }}</span>
                                <span class="shrink-0">{{ Formato::dinero($item['producto']->precio === null ? null : $item['producto']->precio * $item['cantidad'], 'IMPORTE') }}</span>
                            </li>
                        @endforeach
                    </ul>

                    <p class="mt-2 rounded-lg bg-harina px-3 py-2 text-xs font-semibold">{{ $this->cajas }} cajas · Nivel {{ $this->nivel }} aplicado</p>

                    <dl class="mt-4 space-y-1.5 text-sm">
                        <div class="flex justify-between"><dt>Subtotal de lista</dt><dd>$ [SUBTOTAL]</dd></div>
                        <div class="flex justify-between font-semibold text-albahaca"><dt>Descuento {{ $this->nivel }}</dt><dd>− $ [DESCUENTO]</dd></div>
                        <div class="flex justify-between"><dt>Envío</dt><dd>{{ $entrega === 'retiro' ? 'Sin cargo' : '$ [ENVÍO]' }}</dd></div>
                        <div class="flex justify-between"><dt>IVA</dt><dd>$ [IVA]</dd></div>
                    </dl>
                    <div class="mt-3 flex items-end justify-between border-t-2 border-carbon pt-3">
                        <span class="text-sm font-bold">Total</span>
                        <span class="font-display text-3xl">$ [TOTAL]</span>
                    </div>

                    <button type="submit" class="tienda-btn-primario mt-4 w-full" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="confirmar">Confirmar pedido</span>
                        <span wire:loading wire:target="confirmar">Enviando…</span>
                    </button>
                    <p class="mt-3 text-center text-xs text-tinta">Vas a recibir la confirmación y el remito por email y WhatsApp.</p>
                </div>
            </aside>
        </form>
    @endif
</div>
