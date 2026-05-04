<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Services\ImgBBService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ProductoController extends Controller
{
    public function index(Request $request)
    {
        try {
            $with = ['proveedor', 'categorias', 'imagenes'];
            if ($request->filled('grupo_variante')) {
                $with[] = 'atributos';
            }
            $query = Producto::with($with);

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('nombre',      'LIKE', "%{$search}%")
                      ->orWhere('sku',       'LIKE', "%{$search}%")
                      ->orWhere('marca',     'LIKE', "%{$search}%")
                      ->orWhere('color',     'LIKE', "%{$search}%")
                      ->orWhere('descripcion','LIKE',"%{$search}%")
                      ->orWhere('ubicacion', 'LIKE', "%{$search}%");
                });
            }

            if ($request->filled('estado') && $request->estado !== 'todos') {
                $query->where('estado', $request->estado);
            }

            if ($request->filled('proveedor_id')) {
                $query->where('proveedor_id', $request->proveedor_id);
            }

            if ($request->filled('categoria_id')) {
                $query->whereHas('categorias', fn($q) => $q->where('categorias.id', $request->categoria_id));
            }

            if ($request->filled('grupo_variante')) {
                $query->where('grupo_variante', $request->grupo_variante);
            }

            if ($request->filled('stock')) {
                match ($request->stock) {
                    'bajo'       => $query->whereRaw('stock > 0 AND stock <= stock_minimo'),
                    'disponible' => $query->where('stock', '>', 0),
                    'agotado'    => $query->where('stock', '<=', 0),
                    default      => null,
                };
            }

            match ($request->get('sort', 'nombre_asc')) {
                'nombre_desc' => $query->orderBy('nombre', 'desc'),
                'precio_asc'  => $query->orderBy('precio_venta', 'asc'),
                'precio_desc' => $query->orderBy('precio_venta', 'desc'),
                'stock_asc'   => $query->orderBy('stock', 'asc'),
                'stock_desc'  => $query->orderBy('stock', 'desc'),
                default       => $query->orderBy('nombre', 'asc'),
            };

            $countQuery = clone $query;
            $byEstado   = $countQuery->reorder()
                ->selectRaw('estado, COUNT(*) as cnt')
                ->groupBy('estado')
                ->pluck('cnt', 'estado');

            $activos   = (int) ($byEstado['activo']   ?? 0);
            $inactivos = (int) ($byEstado['inactivo'] ?? 0);
            $stockBajo = (clone $query)->reorder()->whereRaw('stock > 0 AND stock <= stock_minimo')->count();
            $agotados  = (clone $query)->reorder()->where('stock', '<=', 0)->count();
            $enOferta  = (clone $query)->reorder()->whereNotNull('precio_oferta')->whereRaw('precio_oferta > 0')->count();

            $productos = $query->paginate($request->get('per_page', 20));

            return response()->json([
                'success'   => true,
                'productos' => $productos,
                'counts'    => [
                    'total'      => $activos + $inactivos,
                    'activos'    => $activos,
                    'inactivos'  => $inactivos,
                    'stock_bajo' => $stockBajo,
                    'agotados'   => $agotados,
                    'en_oferta'  => $enOferta,
                ],
                'message'   => 'Filtrado exitoso',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al filtrar productos',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sku'              => 'required|string|max:50|unique:productos',
            'nombre'           => 'required|string|max:200',
            'descripcion'      => 'nullable|string',
            'especificaciones' => 'nullable|string',
            'marca'            => 'nullable|string|max:100',
            'color'            => 'nullable|string|max:50',
            'grupo_variante'   => 'nullable|string|max:100',
            'proveedor_id'     => 'required|exists:proveedores,id',
            'precio_compra'    => 'required|numeric|min:0',
            'precio_venta'     => 'required|numeric|min:0',
            'precio_oferta'    => 'nullable|numeric|min:0',
            'estado'           => 'required|in:activo,inactivo',
            'stock'            => 'required|integer|min:0',
            'stock_minimo'     => 'required|integer|min:0',
            'codigo_barras'    => 'nullable|string|max:100|unique:productos',
            'ubicacion'        => 'nullable|string|max:100',
            'notas_internas'   => 'nullable|string',
            'garantia'         => 'nullable|string|max:50',
            'categorias'       => 'required|array',
            'categorias.*'     => 'exists:categorias,id',
            'atributos'        => 'nullable|array',
            'atributos.*.nombre' => 'required_with:atributos|string|max:100',
            'atributos.*.valor'  => 'required_with:atributos|string|max:200',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors'  => $validator->errors()
            ], 422);
        }

        $producto = Producto::create($request->except(['categorias', 'atributos']));

        if ($request->has('categorias')) {
            $producto->categorias()->sync($request->categorias);
        }

        $atributos = array_filter($request->get('atributos', []), fn($a) => !empty($a['nombre']) && $a['valor'] !== '');
        foreach ($atributos as $attr) {
            $producto->atributos()->create(['nombre' => $attr['nombre'], 'valor' => $attr['valor']]);
        }

        if (!empty($atributos) && !$producto->grupo_variante) {
            $producto->update(['grupo_variante' => 'gv-' . $producto->id]);
        }

        $producto->load(['proveedor', 'categorias', 'atributos']);

        return response()->json([
            'message'  => 'Producto creado exitosamente',
            'producto' => $producto
        ], 201);
    }

    public function show(string $id)
    {
        $producto = Producto::with(['proveedor', 'categorias', 'imagenes', 'atributos'])->find($id);

        if (!$producto) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        return response()->json(['producto' => $producto]);
    }

    public function update(Request $request, string $id)
    {
        $producto = Producto::find($id);

        if (!$producto) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'sku'              => 'sometimes|string|max:50|unique:productos,sku,' . $id,
            'nombre'           => 'sometimes|string|max:200',
            'descripcion'      => 'nullable|string',
            'especificaciones' => 'nullable|string',
            'marca'            => 'nullable|string|max:100',
            'color'            => 'nullable|string|max:50',
            'grupo_variante'   => 'nullable|string|max:100',
            'proveedor_id'     => 'sometimes|exists:proveedores,id',
            'precio_compra'    => 'sometimes|numeric|min:0',
            'precio_venta'     => 'sometimes|numeric|min:0',
            'precio_oferta'    => 'nullable|numeric|min:0',
            'estado'           => 'sometimes|in:activo,inactivo',
            'stock'            => 'sometimes|integer|min:0',
            'stock_minimo'     => 'sometimes|integer|min:0',
            'codigo_barras'    => 'nullable|string|max:100|unique:productos,codigo_barras,' . $id,
            'ubicacion'        => 'nullable|string|max:100',
            'notas_internas'   => 'nullable|string',
            'garantia'         => 'nullable|string|max:50',
            'categorias'       => 'sometimes|array',
            'categorias.*'     => 'exists:categorias,id',
            'atributos'        => 'nullable|array',
            'atributos.*.nombre' => 'required_with:atributos|string|max:100',
            'atributos.*.valor'  => 'required_with:atributos|string|max:200',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors'  => $validator->errors()
            ], 422);
        }

        $producto->update($request->except(['categorias', 'atributos']));

        if ($request->has('categorias')) {
            $producto->categorias()->sync($request->categorias);
        }

        if ($request->has('atributos')) {
            $producto->atributos()->delete();
            $atributos = array_filter($request->atributos ?? [], fn($a) => !empty($a['nombre']) && $a['valor'] !== '');
            foreach ($atributos as $attr) {
                $producto->atributos()->create(['nombre' => $attr['nombre'], 'valor' => $attr['valor']]);
            }
        }

        $producto->load(['proveedor', 'categorias', 'atributos']);

        return response()->json([
            'message'  => 'Producto actualizado exitosamente',
            'producto' => $producto
        ]);
    }

    public function destroy(string $id)
    {
        $producto = Producto::find($id);

        if (!$producto) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        $producto->imagenes()->delete();
        $producto->delete();

        return response()->json(['message' => 'Producto eliminado exitosamente']);
    }

    public function changeStatus(Request $request, string $id)
    {
        $producto = Producto::find($id);

        if (!$producto) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'estado' => 'required|in:activo,inactivo',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors'  => $validator->errors()
            ], 422);
        }

        $producto->update(['estado' => $request->estado]);

        return response()->json([
            'message'  => 'Estado del producto actualizado exitosamente',
            'producto' => $producto
        ]);
    }

    public function search(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:2',
            'tipo'  => 'nullable|in:nombre,sku,codigo_barras,ubicacion,todos',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors'  => $validator->errors()
            ], 422);
        }

        $query = $request->query('query');
        $tipo  = $request->query('tipo', 'todos');

        $productos = Producto::with(['proveedor', 'imagenPrincipal'])->where('estado', 'activo');

        match ($tipo) {
            'nombre'        => $productos->where('nombre',        'like', "%{$query}%"),
            'sku'           => $productos->where('sku',           'like', "%{$query}%"),
            'codigo_barras' => $productos->where('codigo_barras', 'like', "%{$query}%"),
            'ubicacion'     => $productos->where('ubicacion',     'like', "%{$query}%"),
            default         => $productos->where(fn($q) =>
                $q->where('nombre',        'like', "%{$query}%")
                  ->orWhere('sku',          'like', "%{$query}%")
                  ->orWhere('codigo_barras','like', "%{$query}%")
                  ->orWhere('ubicacion',    'like', "%{$query}%")
            ),
        };

        $resultados = $productos->orderBy('nombre')->limit(20)->get();

        return response()->json(['productos' => $resultados, 'total' => $resultados->count()]);
    }

    public function stockBajo()
    {
        $productos = Producto::with(['proveedor', 'imagenPrincipal'])
            ->stockBajo()
            ->where('estado', 'activo')
            ->orderBy('stock')
            ->get();

        return response()->json(['productos' => $productos, 'total' => $productos->count()]);
    }

    public function uploadImages(Request $request, string $id)
    {
        $producto = Producto::find($id);

        if (!$producto) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'imagenes'     => 'required|array|max:10',
            'imagenes.*'   => 'image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'es_principal' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors'  => $validator->errors()
            ], 422);
        }

        $imgbbService    = new ImgBBService();
        $imagenesSubidas = [];
        $errors          = [];

        foreach ($request->file('imagenes') as $index => $imagen) {
            try {
                $result = $imgbbService->uploadImage($imagen);

                if (!$result['success']) {
                    $errors[] = ['index' => $index, 'nombre' => $imagen->getClientOriginalName(), 'error' => $result['error'] ?? 'Error desconocido'];
                    continue;
                }

                $d = $result['data'];

                $imagenProducto = $producto->imagenes()->create([
                    'url'             => $d['url'],
                    'url_thumb'       => $d['thumb']['url']  ?? $d['url'],
                    'url_medium'      => $d['medium']['url'] ?? $d['url'],
                    'url_delete'      => $d['delete_url']    ?? null,
                    'nombre_original' => $imagen->getClientOriginalName(),
                    'mime_type'       => $imagen->getMimeType(),
                    'tamaño'          => $imagen->getSize(),
                    'es_principal'    => false,
                    'orden'           => $producto->imagenes()->count(),
                    'imgbb_id'        => $d['id']            ?? null,
                    'imgbb_data'      => json_encode($d),
                    'disk'            => 'imgbb',
                ]);

                $imagenesSubidas[] = $imagenProducto;

                Log::info('Imagen subida a ImgBB para producto ID: ' . $id, ['imgbb_id' => $d['id'] ?? null, 'url' => $d['url']]);

            } catch (\Exception $e) {
                Log::error('Error procesando imagen ' . $index, ['error' => $e->getMessage(), 'producto_id' => $id]);
                $errors[] = ['index' => $index, 'nombre' => $imagen->getClientOriginalName(), 'error' => $e->getMessage()];
            }
        }

        if ($request->filled('es_principal')) {
            $this->setImagenPrincipal(new Request(), $id, $request->es_principal);
        }

        $response = [
            'message'          => 'Imágenes procesadas',
            'imagenes_subidas' => count($imagenesSubidas),
            'imagenes'         => $imagenesSubidas,
        ];

        if (!empty($errors)) {
            $response['errors']  = $errors;
            $response['message'] = 'Algunas imágenes no se pudieron subir';
        }

        return response()->json($response, 201);
    }

    public function setImagenPrincipal(Request $request, string $id, string $imagenId)
    {
        $producto = Producto::find($id);

        if (!$producto) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        $imagen = $producto->imagenes()->find($imagenId);

        if (!$imagen) {
            return response()->json(['message' => 'Imagen no encontrada'], 404);
        }

        $producto->imagenes()->update(['es_principal' => false]);
        $imagen->update(['es_principal' => true]);

        return response()->json(['message' => 'Imagen principal establecida exitosamente', 'imagen' => $imagen]);
    }

    public function deleteImage(string $id, string $imagenId)
    {
        $producto = Producto::find($id);

        if (!$producto) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        $imagen = $producto->imagenes()->find($imagenId);

        if (!$imagen) {
            return response()->json(['message' => 'Imagen no encontrada'], 404);
        }

        $esPrincipal = $imagen->es_principal;
        $imagen->delete();

        if ($esPrincipal && $producto->imagenes()->count() > 0) {
            $producto->imagenes()->first()->update(['es_principal' => true]);
        }

        return response()->json(['message' => 'Imagen eliminada exitosamente']);
    }

    public function reorderImages(Request $request, string $id)
    {
        $producto = Producto::find($id);

        if (!$producto) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'orden'          => 'required|array',
            'orden.*.id'     => 'required|exists:imagenes_producto,id',
            'orden.*.orden'  => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors'  => $validator->errors()
            ], 422);
        }

        foreach ($request->orden as $item) {
            $producto->imagenes()->where('id', $item['id'])->update(['orden' => $item['orden']]);
        }

        return response()->json(['message' => 'Imágenes reordenadas exitosamente']);
    }
}
