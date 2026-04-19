<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sucursal extends Model
{
    protected $table = 'sucursales';

    protected $fillable = [
        'nombre', 'direccion', 'municipio', 'departamento',
        'referencia', 'horario', 'lat', 'lng', 'telefono', 'estado',
    ];

    public function usuarios()
    {
        return $this->hasMany(User::class);
    }

    public function ventas()
    {
        return $this->hasMany(Venta::class);
    }
}
