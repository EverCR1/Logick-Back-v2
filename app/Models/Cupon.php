<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Cupon extends Model
{
    protected $table = 'cupones';

    protected $fillable = [
        'codigo', 'descripcion', 'tipo', 'valor',
        'minimo_compra', 'maximo_descuento',
        'usos_maximos', 'usos_actuales', 'usos_por_cuenta',
        'solo_primera_compra', 'es_publico',
        'fecha_inicio', 'fecha_vencimiento', 'estado',
    ];

    protected $casts = [
        'valor'               => 'decimal:2',
        'minimo_compra'       => 'decimal:2',
        'maximo_descuento'    => 'decimal:2',
        'solo_primera_compra' => 'boolean',
        'es_publico'          => 'boolean',
        'fecha_inicio'        => 'datetime',
        'fecha_vencimiento'   => 'datetime',
    ];

    // ── Relaciones ─────────────────────────────────────────────────────────────

    public function cuentas()
    {
        return $this->belongsToMany(Cuenta::class, 'cuenta_cupones')
                    ->withPivot('usos')
                    ->withTimestamps();
    }

    public function pedidos()
    {
        return $this->hasMany(Pedido::class);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    public function estaVigente(): bool
    {
        if ($this->estado !== 'activo') return false;

        $ahora = Carbon::now();

        if ($this->fecha_inicio && $ahora->lt($this->fecha_inicio)) return false;
        if ($this->fecha_vencimiento && $ahora->gt($this->fecha_vencimiento)) return false;

        if ($this->usos_maximos !== null && $this->usos_actuales >= $this->usos_maximos) return false;

        return true;
    }

    /**
     * Calcula el descuento real a aplicar sobre un subtotal dado.
     */
    public function calcularDescuento(float $subtotal): float
    {
        if ($this->minimo_compra && $subtotal < $this->minimo_compra) return 0;

        $descuento = $this->tipo === 'porcentaje'
            ? $subtotal * ($this->valor / 100)
            : (float) $this->valor;

        if ($this->maximo_descuento) {
            $descuento = min($descuento, (float) $this->maximo_descuento);
        }

        return round(min($descuento, $subtotal), 2);
    }
}