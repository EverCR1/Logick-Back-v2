<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImagenServicio extends Model
{
    protected $table = 'imagenes_servicio';

    protected $fillable = [
        'servicio_id', 'url', 'url_thumb', 'url_medium', 'url_delete',
        'imgbb_id', 'imgbb_data', 'disk', 'nombre_original', 'mime_type', 'tamaño', 'descripcion',
    ];

    protected $casts = ['tamaño' => 'integer'];

    public function servicio()
    {
        return $this->belongsTo(Servicio::class);
    }
}
