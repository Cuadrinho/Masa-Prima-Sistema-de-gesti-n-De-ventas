@props(['title' => null, 'migas' => []])

@php
    use App\Enums\EstadoPedido;
    use App\Models\Order;

    $hoy = today();
    $contadores = [
        'solicitudes' => Order::where('estado', EstadoPedido::Solicitud)->count(),
        'cocina' => Order::whereIn('estado', [EstadoPedido::PorPreparar, EstadoPedido::EnHorno])->count(),
        'entregas' => Order::activos()->where('estado', '!=', EstadoPedido::Solicitud)->whereDate('entrega_fecha', '<=', $hoy)->count(),
    ];

    $menu = [
        ['Panel general', route('admin.panel'), request()->routeIs('admin.panel'), null, 'M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z'],
        ['Solicitudes', route('admin.pedidos', ['estado' => 'solicitud']), request()->routeIs('admin.pedidos') && request('estado') === 'solicitud', $contadores['solicitudes'], 'M4 5h16v11H8l-4 4z'],
        ['Pedidos', route('admin.pedidos'), request()->routeIs('admin.pedidos', 'admin.pedido') && request('estado') !== 'solicitud', null, 'M6 3h12v18H6zM9 8h6M9 12h6M9 16h4'],
        ['Cocina', route('admin.cocina'), request()->routeIs('admin.cocina'), $contadores['cocina'], 'M8 10V7a4 4 0 0 1 8 0v3M5 10h14l-1 10H6z'],
        ['Entregas', route('admin.entregas'), request()->routeIs('admin.entregas'), $contadores['entregas'], 'M3 6h11v10H3zM14 10h4l3 3v3h-7M7 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4M17 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4'],
        ['Cobranzas', route('admin.entregas').'#cobranzas', false, null, 'M3 6h18v12H3zM3 10h18M7 15h4'],
        ['Finalizados', route('admin.finalizados'), request()->routeIs('admin.finalizados'), null, 'm5 12 5 5L20 7'],
        ['Clientes', null, false, null, 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8M4 20a8 8 0 0 1 16 0'],
        ['Catálogo y precios', null, false, null, 'M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z'],
    ];

    $usuario = auth()->user();
@endphp

<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />

        <title>{{ filled($title) ? $title.' - ' : '' }}Panel interno - {{ config('tienda.nombre') }}</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-harina font-body text-carbon antialiased" x-data="{ menu: false }">
        {{-- Barra lateral --}}
        <aside
            class="fixed inset-y-0 left-0 z-40 flex w-60 -translate-x-full flex-col bg-carbon px-4 py-5 text-white/80 transition lg:translate-x-0"
            :class="menu && 'translate-x-0'"
        >
            <a href="{{ route('admin.panel') }}" wire:navigate class="flex items-center gap-3 border-b border-white/10 px-1 pb-5">
                <img src="{{ asset('images/logo.jpg') }}" alt="" class="size-11 shrink-0 rounded-full ring-1 ring-white/20">
                <span class="leading-none">
                    <span class="block font-display text-xl uppercase tracking-wide text-white">{{ config('tienda.nombre') }}</span>
                    <span class="mt-1 block text-[0.6rem] font-bold uppercase tracking-[0.2em] text-queso">Panel interno</span>
                </span>
            </a>

            <nav class="mt-4 flex-1 space-y-1">
                @foreach ($menu as [$texto, $url, $activo, $contador, $icono])
                    @if ($url)
                        <a
                            href="{{ $url }}"
                            wire:navigate
                            @class([
                                'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition',
                                'bg-tomate text-white' => $activo,
                                'hover:bg-white/5 hover:text-white' => ! $activo,
                            ])
                        >
                    @else
                        <span class="flex cursor-not-allowed items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-white/40" title="Próximamente">
                    @endif
                        <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icono }}" /></svg>
                        <span class="flex-1">{{ $texto }}</span>
                        @if ($contador)
                            <span @class(['rounded-full px-2 text-xs font-bold', 'bg-white text-tomate' => $activo, 'bg-queso text-carbon' => ! $activo])>{{ $contador }}</span>
                        @elseif (! $url)
                            <span class="text-[0.6rem] uppercase tracking-wider">Pronto</span>
                        @endif
                    @if ($url)
                        </a>
                    @else
                        </span>
                    @endif
                @endforeach
            </nav>

            <div class="space-y-4">
                <p class="flex items-center gap-2 rounded-lg bg-white/5 px-3 py-2.5 text-xs">
                    <span class="size-2 shrink-0 rounded-full bg-green-500"></span>
                    Planta abierta · Turno {{ now()->hour < 13 ? 'mañana' : 'tarde' }}
                </p>
                <div class="flex items-center gap-3 px-1">
                    <span class="flex size-9 items-center justify-center rounded-full bg-queso font-bold text-carbon">{{ mb_substr($usuario->name, 0, 1) }}</span>
                    <span class="flex-1 leading-tight">
                        <span class="block text-sm font-semibold text-white">{{ $usuario->name }}</span>
                        <span class="block text-xs">{{ $usuario->rol->etiqueta() }}</span>
                    </span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded p-1.5 hover:bg-white/10 hover:text-white" title="Cerrar sesión">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 4h4v16h-4M10 8l-4 4 4 4M6 12h11" /></svg>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <div x-show="menu" x-on:click="menu = false" class="fixed inset-0 z-30 bg-carbon/50 lg:hidden" x-cloak></div>

        <div class="lg:pl-60">
            {{-- Barra superior --}}
            <header class="sticky top-0 z-20 flex items-center gap-4 border-b border-linea bg-white px-4 py-3 sm:px-8">
                <button type="button" x-on:click="menu = true" class="-ml-1 rounded p-1.5 hover:bg-harina lg:hidden" aria-label="Abrir menú">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16" /></svg>
                </button>

                <nav class="flex min-w-0 items-center gap-2 text-sm font-semibold">
                    @foreach ($migas as $i => $miga)
                        @if ($i > 0)
                            <span class="text-tinta/50">/</span>
                        @endif
                        <span class="truncate">{{ $miga }}</span>
                    @endforeach
                </nav>

                <form method="GET" action="{{ route('admin.pedidos') }}" class="relative ml-auto hidden w-full max-w-xs md:block">
                    <svg class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-tinta" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" /></svg>
                    <input type="search" name="q" value="{{ request('q') }}" class="tienda-input !py-2 !pl-9" placeholder="Buscar pedido, cliente o código">
                </form>

                <p class="ml-auto shrink-0 text-sm font-medium text-tinta md:ml-0">
                    <span class="hidden sm:inline">{{ App\Support\Formato::fecha(now(), 'l j \d\e F') }} · </span>{{ now()->format('H:i') }}
                </p>
            </header>

            <main class="px-4 py-6 sm:px-8 sm:py-8">
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
