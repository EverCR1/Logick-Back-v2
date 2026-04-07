<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Traits\Auditable;

class Producto extends Model
{
    use Auditable;

    protected $fillable = [
        'sku', 'nombre', 'descripcion', 'especificaciones', 'marca', 'color',
        'proveedor_id', 'precio_compra', 'precio_venta', 'precio_oferta',
        'estado', 'stock', 'stock_minimo', 'codigo_barras', 'ubicacion',
        'notas_internas', 'garantia',
    ];

    protected $casts = [
        'precio_compra' => 'decimal:2',
        'precio_venta'  => 'decimal:2',
        'precio_oferta' => 'decimal:2',
        'stock'         => 'integer',
        'stock_minimo'  => 'integer',
        'es_principal'  => 'boolean',
    ];

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function categorias()
    {
        return $this->belongsToMany(Categoria::class, 'categoria_producto');
    }

    public function imagenes()
    {
        return $this->hasMany(ImagenProducto::class);
    }

    public function imagenPrincipal()
    {
        return $this->hasOne(ImagenProducto::class)->where('es_principal', true);
    }

    public function scopeActivos($query)
    {
        return $query->where('estado', 'activo');
    }

    public function scopeStockBajo($query)
    {
        return $query->where('stock', '<=', DB::raw('stock_minimo'));
    }

    public function getEstaBajoStockAttribute(): bool
    {
        return $this->stock <= $this->stock_minimo;
    }

    public function getPrecioFinalAttribute()
    {
        return $this->precio_oferta ?? $this->precio_venta;
    }

    public function getMargenAttribute(): float
    {
        if ($this->precio_compra > 0) {
            return (($this->precio_final - $this->precio_compra) / $this->precio_compra) * 100;
        }
        return 0;
    }

    public function actualizarStock($cantidad, $tipo = 'venta'): void
    {
        if ($tipo === 'venta') {
            $this->stock -= $cantidad;
        } elseif (in_array($tipo, ['compra', 'entrada'])) {
            $this->stock += $cantidad;
        } elseif ($tipo === 'ajuste') {
            $this->stock = $cantidad;
        }

        $this->save();
    }
}
