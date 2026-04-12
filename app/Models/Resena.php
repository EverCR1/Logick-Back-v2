<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Resena extends Model
{
    protected $table = 'resenas';

    protected $fillable = [
        'cuenta_id', 'producto_id', 'pedido_id',
        'rating', 'comentario', 'puntos_otorgados', 'estado',
    ];

    protected $casts = [
        'rating'           => 'integer',
        'puntos_otorgados' => 'integer',
    ];

    public function cuenta()
    {
        return $this->belongsTo(Cuenta::class);
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function pedido()
    {
        return $this->belongsTo(Pedido::class);
    }
}