@props(['oscuro' => false])

<button type="button" disabled title="Próximamente" {{ $attributes->class([$oscuro ? 'tienda-btn-oscuro' : 'tienda-btn-borde', '!py-2.5 cursor-not-allowed opacity-60']) }}>{{ $slot }}</button>
