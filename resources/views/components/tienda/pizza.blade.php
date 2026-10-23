@props(['variante' => 'prepizza'])

@php
    $conQueso = $variante !== 'prepizza';
    $pepperoni = in_array($variante, ['calabresa', 'especial']);
    $aceitunas = in_array($variante, ['muzzarella', 'especial']);
@endphp

<svg viewBox="0 0 100 100" aria-hidden="true" {{ $attributes }}>
    @if ($variante === 'bollos')
        @foreach ([20, 50, 80] as $x)
            <ellipse cx="{{ $x }}" cy="58" rx="14" ry="9" fill="#d4ae78" />
            <ellipse cx="{{ $x }}" cy="53" rx="14" ry="10" fill="#ecd1a4" />
        @endforeach
    @else
        <circle cx="50" cy="50" r="47" fill="#d28c45" />
        <circle cx="50" cy="50" r="44" fill="#e5a95f" />
        <circle cx="50" cy="50" r="37" fill="#c3311b" />

        @if ($conQueso)
            <g fill="#f6d88e">
                <rect x="25" y="27" width="22" height="18" rx="9" />
                <rect x="51" y="25" width="24" height="19" rx="9.5" />
                <rect x="24" y="50" width="25" height="20" rx="10" />
                <rect x="52" y="51" width="22" height="20" rx="10" />
            </g>
        @endif

        @if ($variante === 'napolitana')
            <g fill="#3f8e3f">
                <ellipse cx="44" cy="48" rx="6" ry="3" transform="rotate(-35 44 48)" />
                <ellipse cx="34" cy="44" rx="5" ry="2.6" transform="rotate(30 34 44)" />
                <ellipse cx="66" cy="50" rx="5.5" ry="2.8" transform="rotate(20 66 50)" />
            </g>
        @endif

        @if ($variante === 'fugazzeta')
            <g fill="#fffaf0">
                <rect x="30" y="34" width="20" height="3.5" rx="1.75" transform="rotate(-20 40 36)" />
                <rect x="52" y="40" width="18" height="3.5" rx="1.75" transform="rotate(-25 61 42)" />
                <rect x="30" y="58" width="22" height="3.5" rx="1.75" transform="rotate(-15 41 60)" />
                <rect x="55" y="62" width="16" height="3.5" rx="1.75" transform="rotate(-35 63 64)" />
            </g>
        @endif

        @if ($pepperoni)
            <g fill="#9a2a1a" stroke="#6e1b10" stroke-width="1.5">
                <circle cx="37" cy="37" r="5.5" />
                <circle cx="63" cy="35" r="5.5" />
                <circle cx="36" cy="62" r="5.5" />
                <circle cx="62" cy="63" r="5.5" />
            </g>
        @endif

        @if ($aceitunas)
            <g fill="#3b2a20">
                <circle cx="{{ $variante === 'especial' ? 50 : 41 }}" cy="{{ $variante === 'especial' ? 50 : 39 }}" r="2.6" />
                <circle cx="{{ $variante === 'especial' ? 46 : 60 }}" cy="{{ $variante === 'especial' ? 66 : 36 }}" r="2.6" />
            </g>
        @endif
    @endif
</svg>
