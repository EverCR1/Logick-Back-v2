<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VentaDetalle extends Model
{
    protected $table = 'venta_detalles';

    protected $fillable = [
        'venta_id', 'tipo', 'cantidad', 'descripcion',
        'precio_unitario', 'descuento', 'subtotal', 'total',
        'producto_id', 'servicio_id', 'referencia',
    ];

    protected $casts = [
        'cantidad'       => 'integer',
        'precio_unitario'=> 'decimal:2',
        'descuento'      => 'decimal:2',
        'subtotal'       => 'decimal:2',
        'total'          => 'decimal:2',
    ];

    public function venta()    { return $this->belongsTo(Venta::class); }
    public function producto() { return $this->belongsTo(Producto::class); }
    public function servicio() { return $this->belongsTo(Servicio::class); }

    protected static function booted(): void
    {
        static::creating(function ($detalle) {
            $detalle->subtotal = $detalle->precio_unitario * $detalle->cantidad;
            $detalle->total    = $detalle->subtotal - $detalle->descuento;
        });

        static::created(function ($detalle) {
            if ($detalle->venta) {
                $detalle->venta->save();
            }
            if ($detalle->tipo === 'producto' && in_array($detalle->venta->estado, ['completada', 'pendiente'])) {
                $detalle->actualizarStock();
            }
        });

        static::updated(function ($detalle) {
            if ($detalle->venta) {
                $detalle->venta->save();
            }
        });

        static::deleted(function ($detalle) {
            if ($detalle->venta) {
                $detalle->venta->save();
            }
            if ($detalle->tipo === 'producto') {
                $detalle->revertirStock();
            }
        });
    }

    public function actualizarStock(): void
    {
        if ($this->tipo === 'producto' && $this->producto) {
            $this->producto->actualizarStock($this->cantidad, 'venta');
        }
    }

    public function revertirStock(): void
    {
        if ($this->tipo === 'producto' && $this->producto) {
            $this->producto->actualizarStock($this->cantidad, 'compra');
        }
    }
}
