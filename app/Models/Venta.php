<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class Venta extends Model
{
    use Auditable;

    protected $fillable = [
        'numero_venta', 'cliente_id', 'usuario_id', 'sucursal_id',
        'estado', 'metodo_pago', 'subtotal', 'descuento_total', 'total', 'observaciones',
    ];

    protected $casts = [
        'subtotal'       => 'decimal:2',
        'descuento_total'=> 'decimal:2',
        'total'          => 'decimal:2',
    ];

    public function cliente()  { return $this->belongsTo(Cliente::class); }
    public function usuario()  { return $this->belongsTo(User::class); }
    public function vendedor() { return $this->belongsTo(User::class, 'usuario_id'); }
    public function detalles() { return $this->hasMany(VentaDetalle::class); }
    public function credito()  { return $this->hasOne(Credito::class); }
    public function sucursal() { return $this->belongsTo(Sucursal::class); }

    public function scopeHoy($query)         { return $query->whereDate('created_at', today()); }
    public function scopeCompletadas($query) { return $query->where('estado', 'completada'); }

    public function scopeEstaSemana($query)
    {
        return $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
    }

    public function scopeEsteMes($query)
    {
        return $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year);
    }

    public function scopeBuscar($query, $search)
    {
        return $query->where('numero_venta', 'LIKE', "%{$search}%")
                    ->orWhereHas('cliente', fn($q) => $q->where('nombre', 'LIKE', "%{$search}%"))
                    ->orWhereHas('detalles', fn($q) => $q->where('descripcion', 'LIKE', "%{$search}%"));
    }

    protected static function booted(): void
    {
        static::creating(function ($venta) {
            if (!$venta->numero_venta) {
                $venta->numero_venta = static::generarNumeroVenta();
            }
            if (!$venta->usuario_id && auth()->check()) {
                $venta->usuario_id = auth()->id();
            }
        });

        static::saving(function ($venta) {
            if ($venta->detalles->isNotEmpty()) {
                $venta->subtotal       = $venta->detalles->sum('subtotal');
                $venta->descuento_total= $venta->detalles->sum('descuento');
                $venta->total          = $venta->detalles->sum('total');
            }
        });
    }

    public static function generarNumeroVenta(): string
    {
        $fecha      = now()->format('Ymd');
        $ultimaVenta= static::whereDate('created_at', today())->orderBy('id', 'desc')->first();
        $consecutivo= $ultimaVenta ? intval(substr($ultimaVenta->numero_venta, -5)) + 1 : 1;

        return 'V-' . $fecha . '-' . str_pad($consecutivo, 5, '0', STR_PAD_LEFT);
    }

    public function actualizarStock(): void
    {
        foreach ($this->detalles as $detalle) {
            $detalle->actualizarStock();
        }
    }

    public function revertirStock(): void
    {
        foreach ($this->detalles as $detalle) {
            $detalle->revertirStock();
        }
    }
}
