<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CuentaDireccion extends Model
{
    protected $table = 'cuenta_direcciones';

    protected $fillable = [
        'cuenta_id', 'alias', 'nombre_receptor', 'telefono',
        'departamento', 'municipio', 'direccion', 'referencias', 'es_principal',
    ];

    protected $casts = [
        'es_principal' => 'boolean',
    ];

    public function cuenta()
    {
        return $this->belongsTo(Cuenta::class);
    }
}