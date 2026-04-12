<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoDetalle extends Model
{
    protected $fillable = [
        'pedido_id', 'producto_id',
        'nombre_producto', 'precio_unitario', 'cantidad', 'subtotal',
    ];

    protected $casts = [
        'precio_unitario' => 'float',
        'subtotal'        => 'float',
        'cantidad'        => 'integer',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}