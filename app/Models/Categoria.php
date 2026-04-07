<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class Categoria extends Model
{
    use Auditable;

    protected $fillable = ['nombre', 'descripcion', 'parent_id', 'estado'];

    protected $casts = [
        'estado'    => 'string',
        'parent_id' => 'integer',
    ];

    public function parent()
    {
        return $this->belongsTo(Categoria::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Categoria::class, 'parent_id');
    }

    public function childrenRecursive()
    {
        return $this->hasMany(Categoria::class, 'parent_id')
                    ->with(['childrenRecursive', 'imagen']);
    }

    public function productos()
    {
        return $this->belongsToMany(Producto::class, 'categoria_producto');
    }

    public function imagenes()
    {
        return $this->hasMany(ImagenCategoria::class);
    }

    public function imagen()
    {
        return $this->hasOne(ImagenCategoria::class)->latest();
    }

    public static function getTree()
    {
        return self::with('childrenRecursive', 'imagen')->whereNull('parent_id')->get();
    }

    public static function getFlatTree($parentId = null, $prefix = '', &$result = []): array
    {
        $categories = self::where('parent_id', $parentId)
                          ->where('estado', 'activo')
                          ->orderBy('nombre')
                          ->get();

        foreach ($categories as $category) {
            $result[$category->id] = $prefix . $category->nombre;
            self::getFlatTree($category->id, $prefix . '— ', $result);
        }

        return $result;
    }

    public function puedeEliminarse(): array
    {
        if ($this->children()->count() > 0) {
            return ['puede' => false, 'razon' => 'La categoría tiene subcategorías. Elimínalas primero o reasígnalas.'];
        }

        if ($this->productos()->count() > 0) {
            return ['puede' => false, 'razon' => 'La categoría tiene productos asignados. Desvincula los productos primero.'];
        }

        return ['puede' => true, 'razon' => null];
    }

    public function scopeActivas($query)
    {
        return $query->where('estado', 'activo');
    }

    public function scopeRaiz($query)
    {
        return $query->whereNull('parent_id');
    }
}
