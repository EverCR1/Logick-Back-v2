<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Traits\Auditable;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable, Auditable;

    protected $fillable = [
        'nombres', 'apellidos', 'estado', 'rol',
        'email', 'password', 'username',
        'telefono', 'direccion', 'sucursal_id', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at'     => 'datetime',
    ];

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function ventas()
    {
        return $this->hasMany(Venta::class, 'usuario_id');
    }

    public function getNombreCompletoAttribute(): string
    {
        return "{$this->nombres} {$this->apellidos}";
    }

    public function isAdministrador(): bool { return $this->rol === 'administrador'; }
    public function isVendedor(): bool       { return $this->rol === 'vendedor'; }
    public function isAnalista(): bool       { return $this->rol === 'analista'; }
}
