<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductoAtributo extends Model
{
    protected $table    = 'producto_atributos';
    protected $fillable = ['producto_id', 'nombre', 'valor'];

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }
}
