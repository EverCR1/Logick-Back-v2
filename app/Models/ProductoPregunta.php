<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductoPregunta extends Model
{
    protected $table = 'producto_preguntas';

    protected $fillable = [
        'producto_id', 'cuenta_id',
        'pregunta', 'respuesta', 'respondido_por', 'estado',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function cuenta()
    {
        return $this->belongsTo(Cuenta::class);
    }

    public function respondidoPor()
    {
        return $this->belongsTo(User::class, 'respondido_por');
    }
}