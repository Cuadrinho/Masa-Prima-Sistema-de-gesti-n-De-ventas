<?php

namespace App\Models;

use App\Support\Formato;
use App\Support\Niveles;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Lista de precios. Las publicadas tienen número y rigen desde su fecha; la
 * que no está publicada es el borrador que se edita en el panel.
 *
 * @property int $id
 * @property int|null $numero
 * @property CarbonImmutable $vigente_desde
 * @property CarbonImmutable|null $publicada_at
 * @property string|null $autor
 */
#[Fillable(['numero', 'vigente_desde', 'publicada_at', 'autor'])]
class PriceList extends Model
{
    protected function casts(): array
    {
        return [
            'vigente_desde' => 'date',
            'publicada_at' => 'datetime',
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
     * @param  Builder<PriceList>  $query
     */
    public function scopePublicadas(Builder $query): void
    {
        $query->whereNotNull('publicada_at')->orderByDesc('vigente_desde')->orderByDesc('numero');
    }

    /**
     * Lista que rige hoy: la última publicada cuya fecha ya llegó.
     */
    public static function vigente(): ?self
    {
        return self::publicadas()->whereDate('vigente_desde', '<=', today())->first();
    }

    /**
     * Lista publicada que todavía no empezó a regir, si hay una.
     */
    public static function programada(): ?self
    {
        return self::publicadas()->whereDate('vigente_desde', '>', today())->first();
    }

    public static function borrador(): ?self
    {
        return self::whereNull('publicada_at')->first();
    }

    /**
     * El borrador actual o uno nuevo con los precios de la lista vigente.
     */
    public static function borradorOCrear(): self
    {
        if ($borrador = self::borrador()) {
            return $borrador;
        }

        return DB::transaction(function () {
            $borrador = self::create(['vigente_desde' => today()->next('monday')]);
            $base = self::programada() ?? self::vigente();

            $base?->prices->each(fn (Price $price) => $borrador->prices()->create($price->only('product_id', 'nivel', 'precio')));

            return $borrador;
        });
    }

    /**
     * Precios de la lista por producto y nivel.
     *
     * @return array<int, array<string, string>> product_id => [nivel => precio]
     */
    public function mapa(): array
    {
        $mapa = [];

        foreach ($this->prices as $price) {
            $mapa[$price->product_id][$price->nivel] = $price->precio;
        }

        return $mapa;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public static function mapaVigente(): array
    {
        return self::vigente()?->mapa() ?? [];
    }

    /**
     * Descuento promedio de cada nivel sobre el primero, en porcentaje redondeado.
     *
     * @return array<string, float|null> clave de nivel => descuento, null si no hay precios para comparar
     */
    public function descuentos(): array
    {
        $base = Niveles::claves()[0];
        $mapa = $this->mapa();

        return collect(Niveles::claves())->mapWithKeys(function (string $nivel) use ($base, $mapa) {
            $descuentos = collect($mapa)
                ->filter(fn (array $precios) => isset($precios[$base], $precios[$nivel]) && (float) $precios[$base] > 0)
                ->map(fn (array $precios) => (1 - (float) $precios[$nivel] / (float) $precios[$base]) * 100);

            return [$nivel => $descuentos->isEmpty() ? null : round($descuentos->avg())];
        })->all();
    }

    public function fijar(Product $product, string $nivel, float|string|null $precio): void
    {
        if ($precio === null || $precio === '') {
            $this->prices()->where('product_id', $product->id)->where('nivel', $nivel)->delete();

            return;
        }

        $this->prices()->updateOrCreate(['product_id' => $product->id, 'nivel' => $nivel], ['precio' => $precio]);
    }

    /**
     * Cantidad de precios del borrador que difieren de la lista vigente.
     */
    public function cambiosContra(?self $base): int
    {
        $anterior = $base?->mapa() ?? [];
        $cambios = 0;

        foreach ($this->mapa() as $producto => $niveles) {
            foreach ($niveles as $nivel => $precio) {
                if (! isset($anterior[$producto][$nivel]) || (float) $anterior[$producto][$nivel] !== (float) $precio) {
                    $cambios++;
                }
            }
        }

        return $cambios;
    }

    public function publicar(string $autor): void
    {
        $this->update([
            'numero' => (self::max('numero') ?? 0) + 1,
            'publicada_at' => now(),
            'autor' => $autor,
        ]);
    }

    public function nombre(): string
    {
        return $this->numero ? 'Lista '.$this->numero : 'Borrador';
    }

    /**
     * "lunes 2 de noviembre"
     */
    public function vigenteDesdeTexto(): string
    {
        return mb_strtolower(Formato::fecha($this->vigente_desde, 'l j \d\e F'));
    }
}
