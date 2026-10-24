<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property string $nombre_corto
 * @property string $categoria
 * @property string|null $detalle
 * @property string|null $descripcion
 * @property string $ilustracion
 * @property string|null $imagen
 * @property int|null $unidades_por_caja
 * @property int|null $tamano_cm
 * @property list<string>|null $etiquetas
 * @property bool $activo
 * @property bool $visible_web
 * @property bool $se_puede_pedir
 * @property list<int>|null $dias_produccion 1 (lunes) a 7 (domingo)
 * @property string|null $actualizado_por
 * @property int $orden
 */
#[Fillable([
    'codigo', 'nombre', 'nombre_corto', 'categoria', 'detalle', 'descripcion', 'ilustracion', 'imagen', 'unidades_por_caja', 'tamano_cm',
    'etiquetas', 'activo', 'visible_web', 'se_puede_pedir', 'dias_produccion', 'actualizado_por', 'orden',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'etiquetas' => 'array',
            'activo' => 'boolean',
            'visible_web' => 'boolean',
            'se_puede_pedir' => 'boolean',
            'dias_produccion' => 'array',
        ];
    }

    /**
     * @return HasMany<Price, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(Price::class);
    }

    /**
     * Productos que se muestran en el catálogo de la web.
     *
     * @param  Builder<Product>  $query
     */
    public function scopeActivos(Builder $query): void
    {
        $query->where('activo', true)->where('visible_web', true)->orderBy('orden');
    }

    public function sePuedePedir(): bool
    {
        return $this->activo && $this->visible_web && $this->se_puede_pedir;
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

    /**
     * Nombre de la línea para el panel ("Pizzas").
     */
    public function linea(): string
    {
        return config('tienda.categorias.'.$this->categoria) ?? ucfirst($this->categoria);
    }
}
