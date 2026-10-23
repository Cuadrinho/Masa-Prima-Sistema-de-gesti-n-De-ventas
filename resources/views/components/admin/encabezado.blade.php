@props(['antetitulo' => null, 'titulo'])

<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        @if ($antetitulo)
            <p class="text-xs font-bold uppercase tracking-[0.15em] text-tomate">{{ $antetitulo }}</p>
        @endif
        <h1 class="mt-1 font-display text-5xl uppercase leading-none sm:text-6xl">{{ $titulo }}</h1>
        {{ $bajada ?? '' }}
    </div>
    @isset($acciones)
        <div class="flex flex-wrap gap-2">{{ $acciones }}</div>
    @endisset
</div>
