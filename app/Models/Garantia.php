<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Garantia extends Model
{
    protected $fillable = [
        'numero_garantia', 'cuenta_id', 'pedido_detalle_id',
        'producto_id', 'nombre_producto',
        'tipo', 'descripcion_problema', 'imagenes',
        'estado', 'resolucion',
    ];

    protected $casts = [
        'imagenes' => 'array',
    ];

    public function cuenta()
    {
        return $this->belongsTo(Cuenta::class);
    }

    public function pedidoDetalle()
    {
        return $this->belongsTo(PedidoDetalle::class);
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function mensajes()
    {
        return $this->hasMany(GarantiaMensaje::class)->orderBy('created_at');
    }

    // Genera el próximo número de garantía: GAR-00001
    public static function generarNumero(): string
    {
        $ultimo = static::max('id') ?? 0;
        return 'GAR-' . str_pad($ultimo + 1, 5, '0', STR_PAD_LEFT);
    }
}