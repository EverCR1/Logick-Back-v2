<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PagoCredito extends Model
{
    protected $table = 'pagos_credito';

    protected $fillable = ['credito_id', 'monto', 'fecha_pago', 'tipo', 'observaciones'];

    protected $casts = [
        'monto'      => 'decimal:2',
        'fecha_pago' => 'date',
    ];

    public function credito()
    {
        return $this->belongsTo(Credito::class);
    }
}
