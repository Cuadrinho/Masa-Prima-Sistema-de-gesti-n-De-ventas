@props(['activo' => false, 'etiqueta' => null])

<button type="button" role="switch" aria-checked="{{ $activo ? 'true' : 'false' }}" @if ($etiqueta) aria-label="{{ $etiqueta }}" @endif {{ $attributes->class([
    'relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition',
    'bg-albahaca' => $activo,
    'bg-linea' => ! $activo,
]) }}>
    <span @class(['inline-block size-4.5 rounded-full bg-white shadow transition', 'translate-x-[1.375rem]' => $activo, 'translate-x-[0.1875rem]' => ! $activo])></span>
</button>
