<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReporteProblema extends Model
{
    protected $table = 'reportes_problemas';

    protected $fillable = [
        'cuenta_id', 'categoria', 'descripcion',
        'nombre_contacto', 'email_contacto', 'telefono_contacto',
        'estado', 'puntos_otorgados', 'nota_admin', 'ip_address',
    ];

    protected $casts = [
        'puntos_otorgados' => 'integer',
    ];

    public function cuenta()
    {
        return $this->belongsTo(Cuenta::class);
    }
}
