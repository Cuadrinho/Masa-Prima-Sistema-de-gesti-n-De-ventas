@props(['titulo' => null, 'enlace' => null, 'textoEnlace' => null])

<section {{ $attributes->class('rounded-2xl border border-linea bg-white p-5') }}>
    @if ($titulo)
        <div class="mb-4 flex items-center justify-between gap-3">
            <h2 class="font-display text-2xl uppercase">{{ $titulo }}</h2>
            @if ($enlace)
                <a href="{{ $enlace }}" wire:navigate class="text-sm font-semibold text-tomate underline underline-offset-4">{{ $textoEnlace }} →</a>
            @endif
            {{ $extra ?? '' }}
        </div>
    @endif
    {{ $slot }}
</section>
