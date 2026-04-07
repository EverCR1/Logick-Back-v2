<?php

namespace App\Http\Controllers;

use App\Models\Servicio;
use App\Models\ImagenServicio;
use App\Services\ImgBBService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ServicioController extends Controller
{
    protected ImgBBService $imgBBService;

    public function __construct(ImgBBService $imgBBService)
    {
        $this->imgBBService = $imgBBService;
    }

    public function index(Request $request)
    {
        try {
            $query = Servicio::with('imagenes');

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('codigo',     'LIKE', "%{$search}%")
                      ->orWhere('nombre',   'LIKE', "%{$search}%")
                      ->orWhere('descripcion','LIKE',"%{$search}%");
                });
            }

            if ($request->filled('estado') && $request->estado !== 'todos') {
                $query->where('estado', $request->estado);
            }

            if ($request->filled('precio_min')) {
                $query->where('precio_venta', '>=', $request->precio_min);
            }

            if ($request->filled('precio_max')) {
                $query->where('precio_venta', '<=', $request->precio_max);
            }

            if ($request->filled('margen') && $request->margen !== 'todos') {
                match ($request->margen) {
                    'alto'   => $query->whereRaw('inversion_estimada > 0 AND ((precio_venta - inversion_estimada) / inversion_estimada) * 100 >= 100'),
                    'medio'  => $query->whereRaw('inversion_estimada > 0 AND ((precio_venta - inversion_estimada) / inversion_estimada) * 100 >= 50 AND ((precio_venta - inversion_estimada) / inversion_estimada) * 100 < 100'),
                    'bajo'   => $query->whereRaw('inversion_estimada > 0 AND ((precio_venta - inversion_estimada) / inversion_estimada) * 100 >= 20 AND ((precio_venta - inversion_estimada) / inversion_estimada) * 100 < 50'),
                    'minimo' => $query->whereRaw('inversion_estimada > 0 AND ((precio_venta - inversion_estimada) / inversion_estimada) * 100 < 20'),
                    default  => null,
                };
            }

            match ($request->get('sort', 'nombre_asc')) {
                'nombre_desc'    => $query->orderBy('nombre', 'desc'),
                'precio_asc'     => $query->orderBy('precio_venta', 'asc'),
                'precio_desc'    => $query->orderBy('precio_venta', 'desc'),
                'inversion_asc'  => $query->orderBy('inversion_estimada', 'asc'),
                'inversion_desc' => $query->orderBy('inversion_estimada', 'desc'),
                'margen_asc'     => $query->orderByRaw('((precio_venta - inversion_estimada) / NULLIF(inversion_estimada, 0)) ASC'),
                'margen_desc'    => $query->orderByRaw('((precio_venta - inversion_estimada) / NULLIF(inversion_estimada, 0)) DESC'),
                default          => $query->orderBy('nombre', 'asc'),
            };

            $servicios = $query->paginate($request->get('per_page', 20));

            return response()->json(['success' => true, 'servicios' => $servicios, 'message' => 'Filtrado exitoso']);

        } catch (\Exception $e) {
            Log::error('Error filtrando servicios: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al filtrar servicios',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'codigo'             => 'required|string|max:50|unique:servicios,codigo',
                'nombre'             => 'required|string|max:200',
                'descripcion'        => 'nullable|string',
                'inversion_estimada' => 'required|numeric|min:0',
                'precio_venta'       => 'required|numeric|min:0',
                'precio_oferta'      => 'nullable|numeric|min:0',
                'estado'             => 'required|in:activo,inactivo',
                'notas_internas'     => 'nullable|string',
                'imagen'             => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors'  => $validator->errors()
                ], 422);
            }

            $servicio = Servicio::create($request->only([
                'codigo', 'nombre', 'descripcion', 'inversion_estimada',
                'precio_venta', 'precio_oferta', 'estado', 'notas_internas'
            ]));

            if ($request->hasFile('imagen')) {
                $this->storeImage($servicio->id, $request->file('imagen'));
            }

            return response()->json([
                'success'  => true,
                'servicio' => $servicio->load('imagenes'),
                'message'  => 'Servicio creado exitosamente'
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creando servicio: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al crear servicio',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $servicio = Servicio::with('imagenes')->findOrFail($id);

            return response()->json([
                'success'  => true,
                'servicio' => $servicio,
                'message'  => 'Servicio obtenido exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Servicio no encontrado'], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $servicio = Servicio::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'codigo'             => 'required|string|max:50|unique:servicios,codigo,' . $id,
                'nombre'             => 'required|string|max:200',
                'descripcion'        => 'nullable|string',
                'inversion_estimada' => 'required|numeric|min:0',
                'precio_venta'       => 'required|numeric|min:0',
                'precio_oferta'      => 'nullable|numeric|min:0',
                'estado'             => 'required|in:activo,inactivo',
                'notas_internas'     => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors'  => $validator->errors()
                ], 422);
            }

            $servicio->update($request->only([
                'codigo', 'nombre', 'descripcion', 'inversion_estimada',
                'precio_venta', 'precio_oferta', 'estado', 'notas_internas'
            ]));

            return response()->json([
                'success'  => true,
                'servicio' => $servicio->load('imagenes'),
                'message'  => 'Servicio actualizado exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error actualizando servicio: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar servicio',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $servicio = Servicio::findOrFail($id);
            $servicio->imagenes()->delete();
            $servicio->delete();

            return response()->json(['success' => true, 'message' => 'Servicio eliminado exitosamente']);
        } catch (\Exception $e) {
            Log::error('Error eliminando servicio: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar servicio',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function changeStatus($id)
    {
        try {
            $servicio = Servicio::findOrFail($id);
            $servicio->estado = $servicio->estado === 'activo' ? 'inactivo' : 'activo';
            $servicio->save();

            return response()->json([
                'success'  => true,
                'servicio' => $servicio,
                'message'  => 'Estado del servicio cambiado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al cambiar estado'], 500);
        }
    }

    /**
     * POST /servicios/{id}/upload-image  (ruta HTTP)
     */
    public function uploadImage($id, Request $request)
    {
        try {
            $servicio = Servicio::findOrFail($id);

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

            $servicio->imagenes()->delete();
            $imagenServicio = $this->storeImage($servicio->id, $request->file('imagen'));

            return response()->json([
                'success' => true,
                'imagen'  => $imagenServicio,
                'message' => 'Imagen subida exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error subiendo imagen de servicio: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al subir imagen',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function deleteImage($id, $imagenId)
    {
        try {
            $servicio = Servicio::findOrFail($id);
            $imagen   = $servicio->imagenes()->findOrFail($imagenId);

            if ($imagen->disk === 'imgbb' && $imagen->url_delete) {
                Log::info('Imagen en ImgBB, se requiere cuenta premium para eliminación: ' . $imagen->url_delete);
            }

            $imagen->delete();

            return response()->json(['success' => true, 'message' => 'Imagen eliminada exitosamente']);
        } catch (\Exception $e) {
            Log::error('Error eliminando imagen de servicio: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar imagen',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function search(Request $request)
    {
        try {
            $query  = $request->get('query', '');
            $estado = $request->get('estado', 'todos');

            $servicios = Servicio::with('imagenes')
                ->when($query,  fn($q) => $q->where('codigo',    'LIKE', "%{$query}%")->orWhere('nombre', 'LIKE', "%{$query}%")->orWhere('descripcion', 'LIKE', "%{$query}%"))
                ->when($estado !== 'todos', fn($q) => $q->where('estado', $estado))
                ->latest()
                ->paginate(20);

            return response()->json(['success' => true, 'servicios' => $servicios, 'message' => 'Búsqueda completada']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error en la búsqueda'], 500);
        }
    }

    /**
     * Helper interno para subir imagen a ImgBB y crear el registro.
     */
    private function storeImage(int $servicioId, UploadedFile $file): ImagenServicio
    {
        $result = $this->imgBBService->uploadImage($file);

        if (!$result['success']) {
            throw new \Exception('Error al subir imagen: ' . ($result['error'] ?? 'Error desconocido'));
        }

        $d = $result['data'];

        return ImagenServicio::create([
            'servicio_id'     => $servicioId,
            'url'             => $d['url']           ?? null,
            'url_thumb'       => $d['thumb']['url']  ?? null,
            'url_medium'      => $d['medium']['url'] ?? null,
            'url_delete'      => $d['delete_url']    ?? null,
            'imgbb_id'        => $d['id']            ?? null,
            'imgbb_data'      => json_encode($d),
            'disk'            => 'imgbb',
            'nombre_original' => $file->getClientOriginalName(),
            'mime_type'       => $file->getMimeType(),
            'tamaño'          => $file->getSize(),
        ]);
    }
}
