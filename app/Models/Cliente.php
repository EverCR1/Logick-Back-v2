<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class Cliente extends Model
{
    use Auditable;

    protected $fillable = ['nombre', 'nit', 'email', 'telefono', 'direccion', 'tipo', 'estado', 'notas'];

    public function ventas()
    {
        return $this->hasMany(Venta::class);
    }

    public function scopeActivos($query)
    {
        return $query->where('estado', 'activo');
    }

    public function scopeBuscar($query, $search)
    {
        return $query->where('nombre',   'LIKE', "%{$search}%")
                    ->orWhere('nit',      'LIKE', "%{$search}%")
                    ->orWhere('email',    'LIKE', "%{$search}%")
                    ->orWhere('telefono', 'LIKE', "%{$search}%");
    }

    public function getTotalGastadoAttribute()
    {
        return $this->ventas()->completadas()->sum('total');
    }

    public function getCantidadVentasAttribute()
    {
        return $this->ventas()->completadas()->count();
    }

    public function getUltimaCompraAttribute()
    {
        return $this->ventas()->completadas()->latest()->first();
    }
}
