<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CuentaPunto extends Model
{
    protected $table = 'cuenta_puntos';

    const UPDATED_AT = null;

    protected $fillable = [
        'cuenta_id', 'tipo', 'puntos',
        'referencia_id', 'referencia_type', 'concepto',
    ];

    protected $casts = [
        'puntos' => 'integer',
    ];

    public function cuenta()
    {
        return $this->belongsTo(Cuenta::class);
    }
}