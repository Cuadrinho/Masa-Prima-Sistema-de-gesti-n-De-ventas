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

            <div class="text-sm">
                <p class="font-bold text-white">Contacto</p>
                <ul class="mt-3 space-y-2">
                    <li>WhatsApp [11 6912-7499]</li>
                    <li>[masaprima@gmail.com]</li>
                    <li>[Isidro Casanova 1234]</li>
                </ul>
            </div>

            <div class="text-sm">
                <p class="font-bold text-white">Horario de pedidos</p>
                <ul class="mt-3 space-y-2">
                    <li>Mar a Vie [8:00 - 17:00]</li>
                    <li>Sábados [8:00 - 13:00]</li>
                </ul>
            </div>
        </div>

        <div class="mt-10 border-t border-white/10 pt-6 text-xs">
            © {{ date('Y') }} {{ config('tienda.nombre') }} · [RAZÓN SOCIAL] · CUIT [CUIT]
        </div>
    </div>
</footer>
