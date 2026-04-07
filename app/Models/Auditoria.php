<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Auditoria extends Model
{
    protected $table = 'auditoria';

    protected $fillable = [
        'usuario_id',
        'usuario_nombre',
        'usuario_rol',
        'accion',
        'modulo',
        'tabla',
        'registro_id',
        'descripcion',
        'valores_anteriores',
        'valores_nuevos',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'valores_anteriores' => 'array',
        'valores_nuevos'     => 'array',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function scopeModulo($query, $modulo)
    {
        return $query->where('modulo', $modulo);
    }

    public function scopeAccion($query, $accion)
    {
        return $query->where('accion', $accion);
    }

    public function scopeUsuario($query, $usuarioId)
    {
        return $query->where('usuario_id', $usuarioId);
    }

    public function scopeEntreFechas($query, $fechaInicio, $fechaFin)
    {
        return $query->whereBetween('created_at', [$fechaInicio, $fechaFin]);
    }

    public function scopeBuscar($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('usuario_nombre', 'LIKE', "%{$search}%")
              ->orWhere('descripcion',  'LIKE', "%{$search}%")
              ->orWhere('modulo',       'LIKE', "%{$search}%")
              ->orWhere('accion',       'LIKE', "%{$search}%");
        });
    }

    public function getAccionLegibleAttribute(): string
    {
        return ['CREAR' => 'Creación', 'EDITAR' => 'Edición', 'ELIMINAR' => 'Eliminación', 'CAMBIO_ESTADO' => 'Cambio de estado'][$this->accion] ?? $this->accion;
    }

    public function getIconoAttribute(): string
    {
        return ['CREAR' => 'fa-plus-circle text-success', 'EDITAR' => 'fa-edit text-warning', 'ELIMINAR' => 'fa-trash text-danger', 'CAMBIO_ESTADO' => 'fa-toggle-on text-info'][$this->accion] ?? 'fa-history text-secondary';
    }

    public function getBadgeClassAttribute(): string
    {
        return ['CREAR' => 'bg-success', 'EDITAR' => 'bg-warning text-dark', 'ELIMINAR' => 'bg-danger', 'CAMBIO_ESTADO' => 'bg-info text-dark'][$this->accion] ?? 'bg-secondary';
    }
}
