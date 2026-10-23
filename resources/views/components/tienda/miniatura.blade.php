@props(['producto'])

{{-- Foto redonda del producto, o la ilustración si todavía no tiene foto. --}}
@if ($producto->imagen)
    <img src="{{ asset($producto->imagen) }}" alt="" loading="lazy" {{ $attributes->class('aspect-square rounded-full object-cover') }}>
@else
    <x-tienda.pizza :variante="$producto->ilustracion" {{ $attributes }} />
@endif
