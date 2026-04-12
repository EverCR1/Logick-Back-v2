<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pedido extends Model
{
    protected $fillable = [
        'numero_pedido',
        'nombre', 'telefono', 'email', 'cliente_id',
        'cuenta_id', 'cupon_id', 'descuento_cupon', 'puntos_ganados',
        'departamento', 'municipio', 'direccion', 'referencias',
        'metodo_pago', 'estado',
        'subtotal', 'costo_envio', 'total',
        'notas', 'notas_internas',
        'comprobante_url', 'comprobante_imgbb_id',
    ];

    protected $casts = [
        'subtotal'        => 'float',
        'costo_envio'     => 'float',
        'total'           => 'float',
        'descuento_cupon' => 'float',
        'puntos_ganados'  => 'integer',
    ];

    // ── Relaciones ──────────────────────────────────────────────────────────

    public function detalles(): HasMany
    {
        return $this->hasMany(PedidoDetalle::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(Cuenta::class);
    }

    public function cupon(): BelongsTo
    {
        return $this->belongsTo(Cupon::class);
    }

    public function resenas(): HasMany
    {
        return $this->hasMany(Resena::class);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Genera un número de pedido único: PED-YYYYMM-XXXX
     */
    public static function generarNumero(): string
    {
        $prefijo = 'PED-' . now()->format('Ym') . '-';
        $ultimo  = static::where('numero_pedido', 'like', $prefijo . '%')
                         ->orderByDesc('id')
                         ->value('numero_pedido');

        $siguiente = $ultimo
            ? (int) substr($ultimo, -4) + 1
            : 1;

        return $prefijo . str_pad($siguiente, 4, '0', STR_PAD_LEFT);
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopePendientes($query)
    {
        return $query->where('estado', 'pendiente');
    }

    public function scopeActivos($query)
    {
        return $query->whereNotIn('estado', ['entregado', 'cancelado']);
    }
}