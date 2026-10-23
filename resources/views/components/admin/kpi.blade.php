@props(['titulo', 'valor', 'detalle' => null, 'destacado' => false])

<div @class(['rounded-xl p-5 text-white', 'bg-tomate' => $destacado, 'bg-carbon' => ! $destacado])>
    <p class="text-[0.65rem] font-bold uppercase tracking-[0.15em] text-white/75">{{ $titulo }}</p>
    <p class="mt-2 font-display text-4xl">{{ $valor }}</p>
    @if ($detalle)
        <p class="mt-1 text-xs text-white/75">{{ $detalle }}</p>
    @endif
</div>
