@props(['bajada' => true])

<a href="{{ route('home') }}" {{ $attributes->class('flex items-center gap-3') }}>
    <img src="{{ asset('images/logo.jpg') }}" alt="" class="size-12 shrink-0 rounded-full">

    <span class="leading-none">
        <span class="block font-display text-2xl uppercase tracking-wide">{{ config('tienda.nombre') }}</span>
        @if ($bajada)
            <span class="mt-1 block text-[0.6rem] font-semibold uppercase tracking-[0.2em] text-tinta">{{ config('tienda.bajada') }}</span>
        @endif
    </span>
</a>
