@props(['item', 'codigo' => false])

<span {{ $attributes->class('inline-flex items-center gap-1.5 rounded-full bg-harina py-0.5 pl-0.5 pr-2.5 text-xs font-medium') }}>
    <x-tienda.miniatura :producto="$item->product" class="size-5" />
    {{ $codigo ? $item->product->codigo : $item->product->nombre_corto }} ×{{ $item->cajas }}
</span>
