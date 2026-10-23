<x-layouts::tienda>
    {{-- Barra superior --}}
    <div class="bg-queso px-4 py-2 text-center text-xs font-semibold text-carbon">
        Venta exclusiva a comercios · Pedido mínimo {{ config('tienda.pedido_minimo') }} cajas · Envíos a [La Matanza]
    </div>

    {{-- Encabezado --}}
    <header class="border-b border-linea bg-white">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-6 px-4 py-3 sm:px-6 lg:px-8">
            <x-tienda.logo />

            <nav class="hidden items-center gap-7 text-sm font-medium md:flex">
                <a href="{{ route('catalogo') }}" class="hover:text-tomate">Catálogo</a>
                <a href="#precios" class="hover:text-tomate">Precios por cantidad</a>
                <a href="#como-comprar" class="hover:text-tomate">Cómo comprar</a>
                <a href="#contacto" class="hover:text-tomate">Contacto</a>
            </nav>

            <div class="flex items-center gap-2">
                @auth
                    <a href="{{ route('dashboard') }}" class="tienda-btn-borde !px-4 !py-2.5">Mi cuenta</a>
                @else
                    <a href="{{ route('login') }}" class="tienda-btn-borde !px-4 !py-2.5">Ingresar</a>
                @endauth
                <a href="{{ route('catalogo') }}" class="tienda-btn-oscuro !px-4 !py-2.5">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 7h12l-1 13H7L6 7Z" /><path d="M9 7a3 3 0 0 1 6 0" /></svg>
                    <span class="hidden sm:inline">Mi pedido</span>
                </a>
            </div>
        </div>
    </header>

    {{-- Hero --}}
    <section class="mx-auto grid max-w-6xl items-center gap-12 px-4 py-14 sm:px-6 lg:grid-cols-2 lg:px-8 lg:py-20">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.15em] text-tomate">Fábrica de pizzas · Venta mayorista</p>
            <h1 class="mt-5 font-display text-6xl uppercase leading-[0.92] sm:text-7xl">Pizzas prelistas, listas para tu horno.</h1>
            <p class="mt-6 max-w-md text-[0.95rem] leading-relaxed text-tinta">
                Prepizzas, pizzas precocidas y congeladas para pizzerías, almacenes y revendedores. Armá tu pedido online, elegí el día de entrega y pagá con transferencia o cuenta corriente.
            </p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('catalogo') }}" class="tienda-btn-primario">Ver catálogo y precios</a>
                <a href="#cuenta" class="tienda-btn-borde">Solicitar cuenta mayorista</a>
            </div>
            <dl class="mt-8 flex flex-wrap gap-x-8 gap-y-4 border-t border-linea pt-4">
                <div>
                    <dt class="sr-only">Variedades</dt>
                    <dd class="font-display text-3xl">9</dd>
                    <dd class="text-xs text-tinta">variedades</dd>
                </div>
                <div>
                    <dt class="sr-only">Pedido mínimo</dt>
                    <dd class="font-display text-3xl">{{ config('tienda.pedido_minimo') }} cajas</dd>
                    <dd class="text-xs text-tinta">pedido mínimo</dd>
                </div>
                <div>
                    <dt class="sr-only">Entrega</dt>
                    <dd class="font-display text-3xl">[X] h</dd>
                    <dd class="text-xs text-tinta">entrega en tu local</dd>
                </div>
            </dl>
        </div>

        <div class="relative aspect-square overflow-hidden rounded-3xl bg-tomate">
            <div class="absolute inset-x-0 bottom-0 h-[26%] bg-tomate-oscuro"></div>
            <div class="absolute inset-x-[12%] bottom-[14%] h-[20%] rounded-md bg-carton"></div>
            <x-tienda.pizza variante="especial" class="absolute left-1/2 top-[9%] w-[66%] -translate-x-1/2 drop-shadow-xl" />
            <span class="absolute right-5 top-5 rounded-full bg-queso px-3.5 py-1.5 text-xs font-bold">Caja x [N] unidades</span>
            <p class="absolute bottom-5 left-5 font-display text-2xl uppercase text-white">[Foto de producto]</p>
        </div>
    </section>

    {{-- Clientes --}}
    <section class="bg-harina">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-3 px-4 py-6 sm:px-6 lg:px-8">
            <p class="mr-3 text-xs font-bold uppercase tracking-[0.15em] text-tinta">Le vendemos a</p>
            @foreach (['Pizzerías y rotiserías', 'Almacenes y autoservicios', 'Comedores y catering', 'Revendedores', 'Eventos y clubes'] as $cliente)
                <span class="rounded-full border border-linea bg-white px-4 py-2 text-sm">{{ $cliente }}</span>
            @endforeach
        </div>
    </section>

    {{-- Nuestras pizzas --}}
    <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <h2 class="font-display text-5xl uppercase">Nuestras pizzas</h2>
            <a href="{{ route('catalogo') }}" class="text-sm font-semibold text-tomate underline underline-offset-4">Ver catálogo completo →</a>
        </div>

        <div class="mt-8 grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-5">
            @foreach (App\Models\Product::activos()->get() as $producto)
                <a href="{{ route('catalogo', ['q' => $producto->nombre_corto]) }}" class="group overflow-hidden rounded-xl border border-linea bg-white transition hover:shadow-lg">
                    <div class="aspect-[5/7] overflow-hidden bg-harina">
                        @if ($producto->imagen)
                            <img src="{{ asset($producto->imagen) }}" alt="{{ $producto->nombre }}" loading="lazy" class="size-full object-cover transition group-hover:scale-105">
                        @else
                            <x-tienda.pizza :variante="$producto->ilustracion" class="m-auto mt-8 w-28" />
                        @endif
                    </div>
                    <div class="p-4">
                        <h3 class="font-display text-xl uppercase leading-tight">{{ $producto->nombre }}</h3>
                        <p class="mt-2 text-sm font-semibold text-tomate">Ver en el catálogo →</p>
                    </div>
                </a>
            @endforeach
        </div>
    </section>

    {{-- Precios por volumen --}}
    <section id="precios" class="bg-carbon text-white">
        <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:px-8">
            <p class="text-xs font-bold uppercase tracking-[0.15em] text-queso">Precios por cantidad</p>
            <h2 class="mt-3 max-w-xl font-display text-5xl uppercase leading-none">Más cajas, mejor precio. Automático.</h2>
            <p class="mt-4 max-w-md text-sm leading-relaxed text-white/75">El descuento se aplica solo en el carrito según la cantidad total de cajas del pedido, sin importar la variedad.</p>

            @php($niveles = config('tienda.niveles'))
            <div class="mt-8 grid gap-4 md:grid-cols-3">
                <div class="rounded-xl border border-white/10 p-6">
                    <p class="text-xs font-bold uppercase tracking-[0.15em] text-white/75">{{ $niveles['inicial']['nombre'] }}</p>
                    <p class="mt-3 font-display text-3xl">Hasta {{ $niveles['comercio']['desde'] - 1 }} cajas</p>
                    <p class="mt-2 text-sm text-white/75">Precio de lista mayorista</p>
                </div>
                <div class="relative rounded-xl bg-tomate p-6">
                    <span class="absolute right-4 top-4 rounded-full bg-queso px-2.5 py-1 text-[0.65rem] font-bold text-carbon">Más elegido</span>
                    <p class="text-xs font-bold uppercase tracking-[0.15em]">{{ $niveles['comercio']['nombre'] }}</p>
                    <p class="mt-3 font-display text-3xl">{{ $niveles['comercio']['desde'] }} a {{ $niveles['distribuidor']['desde'] - 1 }} cajas</p>
                    <p class="mt-2 text-sm text-white/85">[−X%] sobre lista</p>
                </div>
                <div class="rounded-xl border border-white/10 p-6">
                    <p class="text-xs font-bold uppercase tracking-[0.15em] text-white/75">{{ $niveles['distribuidor']['nombre'] }}</p>
                    <p class="mt-3 font-display text-3xl">{{ $niveles['distribuidor']['desde'] }} cajas o más</p>
                    <p class="mt-2 text-sm text-white/75">[−X%] sobre lista + precio por contrato</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Cómo comprar --}}
    <section id="como-comprar" class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
        <h2 class="font-display text-5xl uppercase">Cómo comprar</h2>
        <ol class="mt-10 grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['Pedí tu alta', 'Completá el formulario con tu CUIT y te habilitamos la cuenta en [X] horas hábiles.'],
                ['Armá el pedido por caja', 'Mezclá variedades libremente. El descuento por volumen se calcula sobre el total.'],
                ['Confirmá y pagá', 'Transferencia, Mercado Pago o cuenta corriente para clientes aprobados. Factura A o B.'],
                ['Recibí en tu local', 'Reparto refrigerado en la franja horaria que elijas, o retiro por fábrica.'],
            ] as $i => [$titulo, $texto])
                <li class="border-t-[3px] border-tomate pt-4">
                    <p class="font-display text-4xl text-tomate">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</p>
                    <h3 class="mt-2 font-bold">{{ $titulo }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-tinta">{{ $texto }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- Cuenta mayorista --}}
    <section id="cuenta" class="bg-harina">
        <div class="mx-auto grid max-w-6xl gap-12 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:px-8 lg:py-20">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.15em] text-tomate">Cuenta mayorista</p>
                <h2 class="mt-3 font-display text-5xl uppercase leading-none">Abrí tu cuenta y comprá a precio de fábrica</h2>
                <p class="mt-5 max-w-md text-[0.95rem] leading-relaxed text-tinta">Con la cuenta ves los precios mayoristas, repetís pedidos anteriores en un clic y descargás tus facturas.</p>
                <ul class="mt-6 space-y-2.5 text-sm">
                    @foreach (['Repetir pedido semanal', 'Cuenta corriente para clientes frecuentes', 'Atención por WhatsApp con tu vendedor'] as $beneficio)
                        <li class="flex items-center gap-3">
                            <svg class="size-4 text-tomate" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5L20 7" /></svg>
                            {{ $beneficio }}
                        </li>
                    @endforeach
                </ul>
            </div>

            {{-- TODO: guardar la solicitud cuando exista el modelo de clientes (Fase 2). --}}
            <form class="rounded-2xl border border-linea bg-white p-6 shadow-sm" onsubmit="return false">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="razon_social" class="tienda-label">Razón social</label>
                        <input id="razon_social" class="tienda-input" placeholder="Ej.: Pizzería Don Carlos S.R.L.">
                    </div>
                    <div>
                        <label for="cuit" class="tienda-label">CUIT</label>
                        <input id="cuit" class="tienda-input" placeholder="30-12345678-9" inputmode="numeric">
                    </div>
                    <div>
                        <label for="tipo" class="tienda-label">Tipo de comercio</label>
                        <select id="tipo" class="tienda-input">
                            <option>Pizzería / rotisería</option>
                            <option>Almacén / autoservicio</option>
                            <option>Comedor / catering</option>
                            <option>Revendedor</option>
                            <option>Eventos / club</option>
                        </select>
                    </div>
                    <div>
                        <label for="localidad" class="tienda-label">Localidad</label>
                        <input id="localidad" class="tienda-input" placeholder="Ej.: Laferrere">
                    </div>
                    <div>
                        <label for="volumen" class="tienda-label">Cajas por semana (aprox.)</label>
                        <select id="volumen" class="tienda-input">
                            <option>Menos de {{ config('tienda.niveles.comercio.desde') }}</option>
                            <option>{{ config('tienda.niveles.comercio.desde') }} a {{ config('tienda.niveles.distribuidor.desde') - 1 }}</option>
                            <option>{{ config('tienda.niveles.distribuidor.desde') }} o más</option>
                        </select>
                    </div>
                    <div>
                        <label for="whatsapp" class="tienda-label">WhatsApp</label>
                        <input id="whatsapp" type="tel" class="tienda-input" placeholder="11 1234-5678">
                    </div>
                    <div>
                        <label for="email" class="tienda-label">Email</label>
                        <input id="email" type="email" class="tienda-input" placeholder="compras@tunegocio.com">
                    </div>
                </div>
                <button type="submit" class="tienda-btn-primario mt-5 w-full">Solicitar alta mayorista</button>
                <p class="mt-3 text-xs text-tinta">Te contactamos en [X] horas hábiles para validar los datos.</p>
            </form>
        </div>
    </section>

    <x-tienda.footer />
</x-layouts::tienda>
