<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Cuenta extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'cuentas';

    protected $fillable = [
        'nombre', 'apellido', 'email', 'password', 'telefono',
        'email_verified_at', 'google_id', 'avatar',
        'puntos_saldo', 'estado',
    ];

    protected $hidden = [
        'password', 'remember_token', 'google_id',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'puntos_saldo'      => 'integer',
        'password'          => 'hashed',
    ];

    // ── Relaciones ─────────────────────────────────────────────────────────────

    public function direcciones()
    {
        return $this->hasMany(CuentaDireccion::class);
    }

    public function direccionPrincipal()
    {
        return $this->hasOne(CuentaDireccion::class)->where('es_principal', true);
    }

    public function favoritos()
    {
        return $this->belongsToMany(Producto::class, 'cuenta_favoritos')
                    ->withTimestamps();
    }

    public function puntos()
    {
        return $this->hasMany(CuentaPunto::class);
    }

    public function pedidos()
    {
        return $this->hasMany(Pedido::class);
    }

    public function resenas()
    {
        return $this->hasMany(Resena::class);
    }

    public function garantias()
    {
        return $this->hasMany(Garantia::class);
    }

    public function cupones()
    {
        return $this->belongsToMany(Cupon::class, 'cuenta_cupones')
                    ->withPivot('usos')
                    ->withTimestamps();
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    public function getNombreCompletoAttribute(): string
    {
        return "{$this->nombre} {$this->apellido}";
    }

    public function estaVerificado(): bool
    {
        return !is_null($this->email_verified_at);
    }
}