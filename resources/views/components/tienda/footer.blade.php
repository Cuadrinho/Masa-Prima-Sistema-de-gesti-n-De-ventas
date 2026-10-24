<footer id="contacto" class="bg-carbon text-white/75">
    <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <p class="font-display text-2xl uppercase text-white">{{ config('tienda.nombre') }}</p>
                <p class="mt-2 max-w-52 text-sm leading-relaxed">{{ config('tienda.nombre') }}, {{ mb_strtolower(config('tienda.bajada')) }}.</p>
            </div>

            <div class="text-sm">
                <p class="font-bold text-white">Comprar</p>
                <ul class="mt-3 space-y-2">
                    <li><a href="{{ route('catalogo') }}" class="underline underline-offset-2 hover:text-white">Catálogo</a></li>
                    <li><a href="{{ route('home') }}#precios" class="underline underline-offset-2 hover:text-white">Precios por volumen</a></li>
                    <li><a href="{{ route('home') }}#como-comprar" class="underline underline-offset-2 hover:text-white">Envíos y pagos</a></li>
                </ul>
            </div>

            @php($empresa = config('tienda.empresa'))
            @if ($empresa['whatsapp'] || $empresa['email'] || $empresa['direccion'])
                <div class="text-sm">
                    <p class="font-bold text-white">Contacto</p>
                    <ul class="mt-3 space-y-2">
                        @if ($empresa['whatsapp'])
                            <li><a href="https://wa.me/{{ preg_replace('/\D/', '', $empresa['whatsapp']) }}" target="_blank" rel="noopener" class="underline underline-offset-2 hover:text-white">WhatsApp {{ $empresa['whatsapp'] }}</a></li>
                        @endif
                        @if ($empresa['email'])
                            <li><a href="mailto:{{ $empresa['email'] }}" class="underline underline-offset-2 hover:text-white">{{ $empresa['email'] }}</a></li>
                        @endif
                        @if ($empresa['direccion'])
                            <li>{{ $empresa['direccion'] }}</li>
                        @endif
                    </ul>
                </div>
            @endif

            @if (config('tienda.horarios'))
                <div class="text-sm">
                    <p class="font-bold text-white">Horario de pedidos</p>
                    <ul class="mt-3 space-y-2">
                        @foreach (config('tienda.horarios') as $dias => $horario)
                            <li>{{ $dias }} {{ $horario }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <div class="mt-10 border-t border-white/10 pt-6 text-xs">
            {{ collect([
                '© '.date('Y').' '.config('tienda.nombre'),
                $empresa['razon_social'],
                $empresa['cuit'] ? 'CUIT '.$empresa['cuit'] : null,
            ])->filter()->implode(' · ') }}
        </div>
    </div>
</footer>
