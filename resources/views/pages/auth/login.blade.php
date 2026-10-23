<x-layouts::tienda title="Ingresar">
    <div class="grid min-h-screen lg:grid-cols-[46%_1fr]">
        {{-- Panel de marca --}}
        <aside class="relative hidden overflow-hidden bg-carbon px-12 py-12 text-white lg:block">
            <a href="{{ route('home') }}" class="relative z-10 flex items-center gap-3">
                <img src="{{ asset('images/logo.jpg') }}" alt="" class="size-14 rounded-full ring-1 ring-white/20">
                <span class="leading-none">
                    <span class="block font-display text-3xl uppercase tracking-wide">{{ config('tienda.nombre') }}</span>
                    <span class="mt-1.5 block text-[0.65rem] font-bold uppercase tracking-[0.2em] text-queso">Pizzas por mayor · Portal de clientes</span>
                </span>
            </a>

            <x-tienda.pizza variante="calabresa" class="absolute right-[6%] top-[7%] w-32 drop-shadow-2xl" />
            <x-tienda.pizza variante="muzzarella" class="absolute -bottom-[6%] right-[4%] w-[52%] max-w-96 drop-shadow-2xl" />

            <div class="relative z-10 mt-20">
                <span class="inline-block rounded-full bg-queso px-3 py-1 text-xs font-bold text-carbon">Exclusivo comercios</span>
                <h1 class="mt-5 font-display text-[7.5rem] uppercase leading-[0.85] xl:text-[9rem]">Pedí<br>por mayor</h1>

                <ul class="mt-10 space-y-4">
                    @foreach ([
                        ['Lista de precios según tu nivel', collect(config('tienda.niveles'))->pluck('nombre')->implode(' · ')],
                        ['Repetí tu último pedido', 'en un clic'],
                        ['Seguí tu entrega', 'del horno a tu local'],
                        ['Tu cuenta corriente', 'saldos, facturas y remitos'],
                    ] as [$titulo, $detalle])
                        <li class="flex items-start gap-3">
                            <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-queso text-carbon">
                                <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5L20 7" /></svg>
                            </span>
                            <span>
                                <span class="block text-sm font-bold">{{ $titulo }}</span>
                                <span class="block text-xs text-white/60">{{ $detalle }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </aside>

        {{-- Formulario --}}
        <main class="flex flex-col bg-harina px-4 py-6 sm:px-8">
            <div class="flex items-center justify-between gap-4">
                <a href="{{ route('home') }}" class="flex items-center gap-2 lg:invisible">
                    <img src="{{ asset('images/logo.jpg') }}" alt="" class="size-10 rounded-full">
                    <span class="font-display text-xl uppercase">{{ config('tienda.nombre') }}</span>
                </a>
                <p class="text-sm text-tinta">¿Dudas? <a href="{{ route('home') }}#contacto" class="font-semibold text-tomate underline underline-offset-2">Escribinos por WhatsApp</a></p>
            </div>

            <div class="flex flex-1 items-center justify-center py-10">
                <div class="w-full max-w-md rounded-2xl border border-linea bg-white p-8 shadow-xl shadow-carbon/5">
                    <p class="text-xs font-bold uppercase tracking-[0.15em] text-tomate">Mi cuenta mayorista</p>
                    <h2 class="mt-2 font-display text-6xl uppercase leading-none">Ingresar</h2>
                    <p class="mt-3 text-sm text-tinta">Entrá para hacer pedidos y ver tu cuenta.</p>

                    @if (session('status'))
                        <p class="mt-4 rounded-lg bg-albahaca-claro px-3 py-2 text-sm font-medium text-albahaca">{{ session('status') }}</p>
                    @endif

                    <div class="mt-6 grid grid-cols-2 rounded-lg border border-linea p-1 text-sm font-semibold">
                        <span class="rounded-md bg-carbon py-2 text-center text-white">CUIT o email</span>
                        <span class="cursor-not-allowed py-2 text-center text-tinta/60" title="Próximamente">Código por WhatsApp <span class="text-[0.6rem] uppercase tracking-wider">· pronto</span></span>
                    </div>

                    <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-5">
                        @csrf

                        <div>
                            <label for="email" class="tienda-label">CUIT o email</label>
                            <div class="relative">
                                <svg class="pointer-events-none absolute left-3 top-1/2 size-4.5 -translate-y-1/2 text-tinta" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2" /><path d="M7 10h4M7 14h7" /></svg>
                                <input
                                    id="email"
                                    name="email"
                                    value="{{ old('email') }}"
                                    required
                                    autofocus
                                    autocomplete="username"
                                    placeholder="30-12345678-9 o compras@tunegocio.com"
                                    @class(['tienda-input !py-3 !pl-10 focus:!border-queso focus:!ring-queso/30', '!border-tomate' => $errors->has('email')])
                                >
                            </div>
                            @error('email')
                                <p class="mt-1.5 text-xs text-tomate">{{ $message === __('auth.failed') ? 'El CUIT, el email o la contraseña no son correctos.' : $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <div class="flex items-baseline justify-between">
                                <label for="password" class="tienda-label">Contraseña</label>
                                @if (Route::has('password.request'))
                                    <a href="{{ route('password.request') }}" class="text-xs font-semibold text-tomate underline underline-offset-2">¿Olvidaste tu contraseña?</a>
                                @endif
                            </div>
                            <div class="relative">
                                <svg class="pointer-events-none absolute left-3 top-1/2 size-4.5 -translate-y-1/2 text-tinta" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2" /><path d="M8 11V7a4 4 0 0 1 8 0v4" /></svg>
                                <input id="password" name="password" type="password" required autocomplete="current-password" class="tienda-input !py-3 !pl-10 !pr-11 focus:!border-queso focus:!ring-queso/30">
                                <button
                                    type="button"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1.5 text-tinta hover:text-carbon"
                                    aria-label="Mostrar contraseña"
                                    onclick="const campo = document.getElementById('password'); campo.type = campo.type === 'password' ? 'text' : 'password'; this.setAttribute('aria-label', campo.type === 'password' ? 'Mostrar contraseña' : 'Ocultar contraseña');"
                                >
                                    <svg class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" /><circle cx="12" cy="12" r="3" /></svg>
                                </button>
                            </div>
                        </div>

                        <label class="flex items-center gap-2.5 text-sm">
                            <input type="checkbox" name="remember" value="1" @checked(old('remember', true)) class="size-4.5 accent-carbon">
                            Recordarme en este equipo
                        </label>

                        <button type="submit" class="tienda-btn-primario w-full !py-3.5 !text-base" data-test="login-button">Ingresar y hacer pedido →</button>
                    </form>

                    <div class="mt-6 flex items-center justify-between gap-4 border-t border-dashed border-linea pt-5">
                        <div>
                            <p class="text-sm font-bold">¿Todavía no sos cliente?</p>
                            <p class="text-xs text-tinta">Pedí el alta mayorista: la aprobamos en 24 a 48 h hábiles.</p>
                        </div>
                        <a href="{{ route('register') }}" class="tienda-btn-borde shrink-0 !px-4 !py-2.5">Solicitar alta</a>
                    </div>
                </div>
            </div>

            <p class="text-center text-xs text-tinta">© {{ date('Y') }} {{ config('tienda.nombre') }} · Venta exclusiva a comercios · Conexión segura</p>
        </main>
    </div>
</x-layouts::tienda>
