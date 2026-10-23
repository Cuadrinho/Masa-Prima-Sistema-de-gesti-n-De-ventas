@props(['clases' => 'bg-arena text-carbon'])

<span {{ $attributes->class(['inline-block whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold', $clases]) }}>{{ $slot }}</span>
