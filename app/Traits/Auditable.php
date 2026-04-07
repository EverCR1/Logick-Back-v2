<?php

namespace App\Traits;

use App\Models\Auditoria;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

trait Auditable
{
    protected static function bootAuditable()
    {
        static::created(function ($model) {
            $model->registrarAuditoria('CREAR', null, $model->toArray());
        });

        static::updated(function ($model) {
            $cambios  = $model->getChanges();
            $original = $model->getOriginal();

            if (count($cambios) === 1 && isset($cambios['estado'])) {
                $model->registrarAuditoria('CAMBIO_ESTADO', $original, $model->toArray());
            } else {
                $model->registrarAuditoria('EDITAR', $original, $model->toArray());
            }
        });

        static::deleted(function ($model) {
            $model->registrarAuditoria('ELIMINAR', $model->toArray(), null);
        });
    }

    protected function registrarAuditoria($accion, $valoresAnteriores, $valoresNuevos): void
    {
        $usuario = Auth::user();

        if (!$usuario) {
            return;
        }

        $tabla   = $this->getTable();
        $modulo  = $this->getModuloFromTable($tabla);

        Auditoria::create([
            'usuario_id'         => $usuario->id,
            'usuario_nombre'     => $usuario->nombres . ' ' . $usuario->apellidos,
            'usuario_rol'        => $usuario->rol,
            'accion'             => $accion,
            'modulo'             => $modulo,
            'tabla'              => $tabla,
            'registro_id'        => $this->id,
            'descripcion'        => $this->generarDescripcion($accion, $modulo),
            'valores_anteriores' => $valoresAnteriores,
            'valores_nuevos'     => $valoresNuevos,
            'ip_address'         => Request::ip(),
            'user_agent'         => Request::userAgent(),
        ]);
    }

    protected function getModuloFromTable(string $tabla): string
    {
        return [
            'users'      => 'usuarios',
            'clientes'   => 'clientes',
            'productos'  => 'productos',
            'servicios'  => 'servicios',
            'proveedores'=> 'proveedores',
            'categorias' => 'categorías',
            'ventas'     => 'ventas',
            'creditos'   => 'créditos',
            'sucursales' => 'sucursales',
        ][$tabla] ?? $tabla;
    }

    protected function generarDescripcion(string $accion, string $modulo): string
    {
        $usuario = Auth::user();
        $nombre  = $usuario ? $usuario->nombres . ' ' . $usuario->apellidos : 'Sistema';

        return [
            'CREAR'        => "{$nombre} creó un nuevo registro en {$modulo}",
            'EDITAR'       => "{$nombre} modificó un registro en {$modulo}",
            'ELIMINAR'     => "{$nombre} eliminó un registro en {$modulo}",
            'CAMBIO_ESTADO'=> "{$nombre} cambió el estado de un registro en {$modulo}",
        ][$accion] ?? "{$nombre} realizó una acción en {$modulo}";
    }

    public function getNombreDescriptivoAttribute(): string
    {
        if (isset($this->nombre)) return $this->nombre;
        if (isset($this->nombres, $this->apellidos)) return "{$this->nombres} {$this->apellidos}";
        if (isset($this->username)) return $this->username;
        if (isset($this->email)) return $this->email;
        return 'Registro #' . $this->id;
    }
}
