<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class Servicio extends Model
{
    use Auditable;

    protected $fillable = [
        'codigo', 'nombre', 'descripcion', 'inversion_estimada',
        'precio_venta', 'precio_oferta', 'estado', 'notas_internas',
    ];

    protected $casts = [
        'inversion_estimada' => 'decimal:2',
        'precio_venta'       => 'decimal:2',
        'precio_oferta'      => 'decimal:2',
    ];

    public function imagenes()
    {
        return $this->hasMany(ImagenServicio::class);
    }

    public function imagenPrincipal()
    {
        return $this->hasOne(ImagenServicio::class)->latest();
    }

    public function scopeActivos($query)
    {
        return $query->where('estado', 'activo');
    }

    public function getPrecioFinalAttribute()
    {
        return $this->precio_oferta ?? $this->precio_venta;
    }

    public function getMargenAttribute(): float
    {
        if ($this->inversion_estimada > 0) {
            return (($this->precio_final - $this->inversion_estimada) / $this->inversion_estimada) * 100;
        }
        return 0;
    }

    public function getEstaEnOfertaAttribute(): bool
    {
        return !is_null($this->precio_oferta);
    }
}
