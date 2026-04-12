<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GarantiaMensaje extends Model
{
    protected $table = 'garantia_mensajes';

    public $timestamps = false;

    protected $fillable = [
        'garantia_id', 'autor_tipo', 'cuenta_id', 'user_id', 'mensaje', 'archivos',
    ];

    protected $casts = [
        'archivos'   => 'array',
        'created_at' => 'datetime',
    ];

    public function garantia()
    {
        return $this->belongsTo(Garantia::class);
    }

    public function cuenta()
    {
        return $this->belongsTo(Cuenta::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}