<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImagenCategoria extends Model
{
    protected $table = 'imagenes_categoria';

    protected $fillable = [
        'categoria_id', 'url', 'url_thumb', 'url_medium', 'url_delete',
        'imgbb_id', 'imgbb_data', 'disk', 'nombre_original', 'mime_type', 'tamaño', 'descripcion',
    ];

    protected $casts = ['tamaño' => 'integer'];

    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }
}
