<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property string $nombre_corto
 * @property string $categoria
 * @property string|null $detalle
 * @property string $ilustracion
 * @property string|null $imagen
 * @property int|null $unidades_por_caja
 * @property string|null $precio
 * @property bool $activo
 * @property int $orden
 */
#[Fillable(['codigo', 'nombre', 'nombre_corto', 'categoria', 'detalle', 'ilustracion', 'imagen', 'unidades_por_caja', 'precio', 'activo', 'orden'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    /**
     * @param  Builder<Product>  $query
     */
    public function scopeActivos(Builder $query): void
    {
        $query->where('activo', true)->orderBy('orden');
    }

    /**
     * Nombre de la categoría en singular, para las etiquetas de las tarjetas.
     */
    public function etiqueta(): string
    {
        return match ($this->categoria) {
            'pizzas' => 'Pizza',
            'prepizzas' => 'Prepizza',
            'precocidas' => 'Precocida',
            'congeladas' => 'Congelada',
            'masas' => 'Masa',
            default => ucfirst($this->categoria),
        };
    }
}
