<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\ImagenCategoria;
use App\Services\ImgBBService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CategoriaController extends Controller
{
    protected ImgBBService $imgBBService;

    public function __construct(ImgBBService $imgBBService)
    {
        $this->imgBBService = $imgBBService;
    }

    public function tree()
    {
        $tree = Categoria::with('childrenRecursive', 'imagen')
                         ->whereNull('parent_id')
                         ->get();

        return response()->json(['categorias' => $tree]);
    }

    public function flat()
    {
        return response()->json(['categorias' => Categoria::getFlatTree()]);
    }

    public function byLevel($level = 0)
    {
        $categorias = $level == 0
            ? Categoria::whereNull('parent_id')->get()
            : $this->getCategoriesByLevel((int) $level);

        return response()->json(['categorias' => $categorias]);
    }

    public function index(Request $request)
    {
        try {
            $query = Categoria::with('imagen', 'parent');

            if ($request->filled('estado') && $request->estado !== 'todos') {
                $query->where('estado', $request->estado);
            }

            if ($request->filled('search')) {
                $query->where('nombre', 'LIKE', '%' . $request->search . '%');
            }

            $categorias = $query->orderBy('nombre')->paginate($request->get('per_page', 50));

            return response()->json(['success' => true, 'categorias' => $categorias]);

        } catch (\Exception $e) {
            Log::error('CategoriaController@index: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener categorías',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nombre'      => 'required|string|max:100',
                'descripcion' => 'nullable|string',
                'parent_id'   => 'nullable|exists:categorias,id',
                'imagen'      => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors'  => $validator->errors()
                ], 422);
            }

            $categoria = Categoria::create([
                'nombre'      => $request->nombre,
                'descripcion' => $request->descripcion,
                'parent_id'   => $request->parent_id ?: null,
                'estado'      => 'activo',
            ]);

            if ($request->hasFile('imagen')) {
                $this->uploadImageToImgBB($categoria->id, $request->file('imagen'));
            }

            return response()->json([
                'success'   => true,
                'categoria' => $categoria->load('imagen', 'parent'),
                'message'   => 'Categoría creada exitosamente'
            ], 201);

        } catch (\Exception $e) {
            Log::error('CategoriaController@store: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al crear categoría',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $categoria = Categoria::with([
                'parent',
                'childrenRecursive',
                'imagen',
                'productos' => fn($q) => $q->with(['imagenes', 'proveedor'])->limit(10),
            ])->findOrFail($id);

            $categoria->productos_count = $categoria->productos()->count();

            return response()->json(['success' => true, 'categoria' => $categoria]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Categoría no encontrada'], 404);
        } catch (\Exception $e) {
            Log::error('CategoriaController@show ' . $id . ': ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener categoría',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $categoria = Categoria::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'nombre'      => 'sometimes|string|max:100',
                'descripcion' => 'nullable|string',
                'parent_id'   => 'nullable|exists:categorias,id',
                'estado'      => 'sometimes|in:activo,inactivo',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors'  => $validator->errors()
                ], 422);
            }

            if ($request->parent_id && (int) $request->parent_id === (int) $id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Una categoría no puede ser su propio padre'
                ], 422);
            }

            $categoria->update([
                'nombre'      => $request->get('nombre',      $categoria->nombre),
                'descripcion' => $request->get('descripcion', $categoria->descripcion),
                'parent_id'   => $request->has('parent_id') ? ($request->parent_id ?: null) : $categoria->parent_id,
                'estado'      => $request->get('estado',      $categoria->estado),
            ]);

            return response()->json([
                'success'   => true,
                'categoria' => $categoria->load('imagen', 'parent'),
                'message'   => 'Categoría actualizada exitosamente'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Categoría no encontrada'], 404);
        } catch (\Exception $e) {
            Log::error('CategoriaController@update ' . $id . ': ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar categoría',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $categoria = Categoria::with(['children', 'productos', 'imagen'])->findOrFail($id);

            if ($categoria->children()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar: la categoría tiene subcategorías. Elimínalas primero.'
                ], 422);
            }

            if ($categoria->productos()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar: la categoría tiene productos asignados.'
                ], 422);
            }

            if ($categoria->imagen) {
                $categoria->imagen->delete();
            }

            $categoria->delete();

            return response()->json(['success' => true, 'message' => 'Categoría eliminada exitosamente']);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Categoría no encontrada'], 404);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar: la categoría está siendo referenciada por otros registros.'
            ], 422);
        } catch (\Exception $e) {
            Log::error('CategoriaController@destroy ' . $id . ': ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar categoría',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function changeStatus(Request $request, $id)
    {
        try {
            $categoria = Categoria::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'estado' => 'required|in:activo,inactivo',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors'  => $validator->errors()
                ], 422);
            }

            $categoria->update(['estado' => $request->estado]);

            return response()->json([
                'success'   => true,
                'categoria' => $categoria,
                'message'   => 'Estado actualizado exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al cambiar estado'], 500);
        }
    }

    public function uploadImage($id, Request $request)
    {
        try {
            $categoria = Categoria::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'imagen' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors'  => $validator->errors()
                ], 422);
            }

            if ($categoria->imagen) {
                $categoria->imagen->delete();
            }

            $imagenCategoria = $this->uploadImageToImgBB($categoria->id, $request->file('imagen'));

            return response()->json([
                'success' => true,
                'imagen'  => $imagenCategoria,
                'message' => 'Imagen subida exitosamente'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Categoría no encontrada'], 404);
        } catch (\Exception $e) {
            Log::error('CategoriaController@uploadImage: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al subir imagen',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function deleteImage($id)
    {
        try {
            $categoria = Categoria::with('imagen')->findOrFail($id);

            if (!$categoria->imagen) {
                return response()->json(['success' => false, 'message' => 'No hay imagen para eliminar'], 404);
            }

            if ($categoria->imagen->disk === 'imgbb' && $categoria->imagen->url_delete) {
                Log::info('Imagen en ImgBB, se requiere cuenta premium para eliminación: ' . $categoria->imagen->url_delete);
            }

            $categoria->imagen->delete();

            return response()->json(['success' => true, 'message' => 'Imagen eliminada exitosamente']);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Categoría no encontrada'], 404);
        } catch (\Exception $e) {
            Log::error('CategoriaController@deleteImage: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar imagen',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    private function uploadImageToImgBB(int $categoriaId, UploadedFile $file): ImagenCategoria
    {
        $result = $this->imgBBService->uploadImage($file);

        if (!$result['success']) {
            throw new \Exception('Error al subir imagen: ' . ($result['error'] ?? 'Error desconocido'));
        }

        $d = $result['data'];

        return ImagenCategoria::create([
            'categoria_id'    => $categoriaId,
            'url'             => $d['url']               ?? null,
            'url_thumb'       => $d['thumb']['url']      ?? null,
            'url_medium'      => $d['medium']['url']     ?? null,
            'url_delete'      => $d['delete_url']        ?? null,
            'imgbb_id'        => $d['id']                ?? null,
            'imgbb_data'      => json_encode($d),
            'disk'            => 'imgbb',
            'nombre_original' => $file->getClientOriginalName(),
            'mime_type'       => $file->getMimeType(),
            'tamaño'          => $file->getSize(),
        ]);
    }

    private function getCategoriesByLevel(int $targetLevel): \Illuminate\Support\Collection
    {
        $result = collect();
        foreach (Categoria::whereNull('parent_id')->get() as $root) {
            $this->collectByLevel($root, 0, $targetLevel, $result);
        }
        return $result;
    }

    private function collectByLevel(Categoria $cat, int $current, int $target, \Illuminate\Support\Collection &$result): void
    {
        if ($current === $target) { $result->push($cat); return; }
        foreach ($cat->children as $child) {
            $this->collectByLevel($child, $current + 1, $target, $result);
        }
    }
}
