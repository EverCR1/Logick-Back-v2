<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImagenProducto extends Model
{
    protected $table = 'imagenes_producto';

    protected $fillable = [
        'producto_id', 'url', 'url_thumb', 'url_medium', 'url_delete',
        'imgbb_id', 'imgbb_data', 'disk', 'nombre_original', 'mime_type',
        'tamaño', 'es_principal', 'orden', 'descripcion',
    ];

    protected $casts = [
        'es_principal' => 'boolean',
        'tamaño'       => 'integer',
        'orden'        => 'integer',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function scopePrincipal($query)
    {
        return $query->where('es_principal', true);
    }

    public function scopeOrdenado($query)
    {
        return $query->orderBy('orden')->orderBy('created_at');
    }
}
