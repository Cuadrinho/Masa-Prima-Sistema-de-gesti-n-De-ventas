@props(['estado'])

<span {{ $attributes->class(['inline-block whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold', $estado->clases()]) }}>{{ $estado->etiqueta() }}</span>
