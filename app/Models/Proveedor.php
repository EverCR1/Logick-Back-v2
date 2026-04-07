<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class Proveedor extends Model
{
    use Auditable;

    protected $table = 'proveedores';

    protected $fillable = ['nombre', 'estado', 'email', 'telefono', 'direccion', 'descripcion'];

    public function productos()
    {
        return $this->hasMany(Producto::class);
    }

    public function getDiasComoProveedorAttribute(): int
    {
        return (int) $this->created_at->diffInDays(now());
    }
}
