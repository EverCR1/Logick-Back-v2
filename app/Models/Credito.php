<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class Credito extends Model
{
    use Auditable;

    protected $fillable = [
        'venta_id',
        'nombre_cliente',
        'capital',
        'producto_o_servicio_dado',
        'fecha_credito',
        'fecha_ultimo_pago',
        'ultima_cantidad_pagada',
        'capital_restante',
        'estado',
    ];

    protected $casts = [
        'capital'                => 'decimal:2',
        'ultima_cantidad_pagada' => 'decimal:2',
        'capital_restante'       => 'decimal:2',
        'fecha_credito'          => 'date',
        'fecha_ultimo_pago'      => 'date',
    ];

    public function venta()
    {
        return $this->belongsTo(Venta::class);
    }

    public function pagos()
    {
        return $this->hasMany(PagoCredito::class)->orderBy('fecha_pago', 'asc');
    }

    public function scopeActivos($query)    { return $query->where('estado', 'activo'); }
    public function scopeAbonados($query)   { return $query->where('estado', 'abonado'); }
    public function scopePagados($query)    { return $query->where('estado', 'pagado'); }

    public function getPorcentajePagadoAttribute(): float
    {
        if ($this->capital > 0) {
            return (($this->capital - $this->capital_restante) / $this->capital) * 100;
        }
        return 0;
    }

    public function registrarPago($monto, $tipo = 'abono', $observaciones = null, $fechaPago = null): PagoCredito
    {
        if ($tipo === 'pago_total') {
            $this->capital_restante      = 0;
            $this->ultima_cantidad_pagada = $this->capital_restante + $monto;
            $this->estado                = 'pagado';
        } else {
            $this->capital_restante      -= $monto;
            $this->ultima_cantidad_pagada = $monto;

            if ($this->capital_restante <= 0) {
                $this->capital_restante = 0;
                $this->estado           = 'pagado';
            } else {
                $this->estado = 'abonado';
            }
        }

        $this->fecha_ultimo_pago = $fechaPago ?? now();
        $this->save();

        if ($this->estado === 'pagado' && $this->venta_id) {
            Venta::where('id', $this->venta_id)
                 ->where('estado', 'pendiente')
                 ->update(['estado' => 'completada']);
        }

        return PagoCredito::create([
            'credito_id'    => $this->id,
            'monto'         => $monto,
            'fecha_pago'    => $fechaPago ?? now(),
            'tipo'          => $tipo,
            'observaciones' => $observaciones,
        ]);
    }
}
