<?php

namespace App\Enums;

enum EstadoPedido: string
{
    case Solicitud = 'solicitud';
    case PorPreparar = 'por_preparar';
    case EnHorno = 'en_horno';
    case Listo = 'listo';
    case EnReparto = 'en_reparto';
    case Entregado = 'entregado';
    case Finalizado = 'finalizado';
    case Rechazado = 'rechazado';

    /**
     * Estados del circuito normal, en orden.
     *
     * @return list<self>
     */
    public static function circuito(): array
    {
        return array_values(array_filter(self::cases(), fn (self $estado) => $estado !== self::Rechazado));
    }

    /**
     * Estados de un pedido que ya está en manos de la cocina.
     *
     * @return list<self>
     */
    public static function enCocina(): array
    {
        return [self::PorPreparar, self::EnHorno, self::Listo];
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Solicitud => 'Nueva solicitud',
            self::PorPreparar => 'Por preparar',
            self::EnHorno => 'En horno',
            self::Listo => 'Listo p/ despachar',
            self::EnReparto => 'En reparto',
            self::Entregado => 'Entregado',
            self::Finalizado => 'Finalizado',
            self::Rechazado => 'Rechazado',
        };
    }

    public function etiquetaCorta(): string
    {
        return match ($this) {
            self::Solicitud => 'Solicitud',
            self::Listo => 'Listo',
            default => $this->etiqueta(),
        };
    }

    /**
     * Clases del badge de estado.
     */
    public function clases(): string
    {
        return match ($this) {
            self::Solicitud => 'bg-queso text-carbon',
            self::PorPreparar => 'bg-arena text-carbon',
            self::EnHorno => 'bg-tomate-claro text-tomate',
            self::Listo => 'border border-albahaca bg-white text-albahaca',
            self::EnReparto => 'bg-carbon text-white',
            self::Entregado => 'bg-albahaca-claro text-albahaca',
            self::Finalizado => 'bg-arena text-tinta',
            self::Rechazado => 'border border-linea bg-white text-tinta line-through',
        };
    }

    /**
     * Clases de la tarjeta del estado en la línea de producción del panel.
     */
    public function clasesTarjeta(): string
    {
        return match ($this) {
            self::Solicitud => 'bg-queso-claro',
            self::PorPreparar, self::Finalizado => 'bg-arena',
            self::EnHorno => 'bg-tomate-claro',
            self::Listo, self::Entregado => 'bg-albahaca-claro',
            self::EnReparto => 'bg-carbon text-white',
            self::Rechazado => 'bg-white',
        };
    }

    public function siguiente(): ?self
    {
        return match ($this) {
            self::Solicitud => self::PorPreparar,
            self::PorPreparar => self::EnHorno,
            self::EnHorno => self::Listo,
            self::Listo => self::EnReparto,
            self::EnReparto => self::Entregado,
            self::Entregado => self::Finalizado,
            self::Finalizado, self::Rechazado => null,
        };
    }

    /**
     * Texto del botón que lleva el pedido al estado siguiente.
     */
    public function accion(): ?string
    {
        return match ($this) {
            self::Solicitud => 'Aceptar → cocina',
            self::PorPreparar => 'Pasar a horno →',
            self::EnHorno => 'Marcar como listo ✓',
            self::Listo => 'Cargar al camión →',
            self::EnReparto => 'Confirmar entrega',
            self::Entregado => 'Finalizar pedido',
            self::Finalizado, self::Rechazado => null,
        };
    }

    /**
     * Columna de fecha que registra cuándo el pedido llegó a este estado.
     */
    public function columnaFecha(): string
    {
        return match ($this) {
            self::Solicitud => 'created_at',
            self::PorPreparar => 'aceptado_at',
            self::EnHorno => 'horno_at',
            self::Listo => 'listo_at',
            self::EnReparto => 'reparto_at',
            self::Entregado => 'entregado_at',
            self::Finalizado => 'finalizado_at',
            self::Rechazado => 'rechazado_at',
        };
    }

    /**
     * Texto que queda en la actividad del pedido al llegar a este estado.
     */
    public function actividad(): string
    {
        return match ($this) {
            self::Solicitud => 'Realizó el pedido online.',
            self::PorPreparar => 'Aceptó el pedido y lo envió a cocina.',
            self::EnHorno => 'Pasó el pedido a horno.',
            self::Listo => 'Marcó el pedido como listo para despachar.',
            self::EnReparto => 'Cargó el pedido al camión.',
            self::Entregado => 'Confirmó la entrega.',
            self::Finalizado => 'Finalizó el pedido.',
            self::Rechazado => 'Rechazó el pedido.',
        };
    }
}
