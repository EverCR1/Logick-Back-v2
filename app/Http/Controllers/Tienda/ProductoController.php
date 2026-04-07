<?php

namespace App\Http\Controllers\Tienda;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use Illuminate\Http\Request;

class ProductoController extends Controller
{
    /**
     * Campos públicos permitidos en el catálogo de la tienda.
     * Se excluyen: precio_compra, ubicacion, notas_internas,
     * stock_minimo, codigo_barras, proveedor_id.
     */
    private array $camposPublicos = [
        'id', 'nombre', 'descripcion', 'especificaciones',
        'marca', 'color', 'precio_venta', 'precio_oferta',
        'stock', 'garantia', 'estado',
    ];

    /**
     * Catálogo paginado con filtros.
     * GET /tienda/productos
     */
    public function index(Request $request)
    {
        try {
            $query = Producto::with(['categorias:id,nombre', 'imagenes' => fn($q) => $q->select([
                'id', 'producto_id', 'url', 'url_thumb', 'url_medium', 'es_principal', 'orden',
            ])->orderBy('orden')])
            ->where('estado', 'activo');

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('nombre',      'LIKE', "%{$search}%")
                      ->orWhere('marca',     'LIKE', "%{$search}%")
                      ->orWhere('descripcion','LIKE', "%{$search}%");
                });
            }

            if ($request->filled('categoria_id')) {
                $query->whereHas('categorias', fn($q) => $q->where('categorias.id', $request->categoria_id));
            }

            if ($request->filled('marca')) {
                $query->where('marca', $request->marca);
            }

            if ($request->filled('precio_min')) {
                $query->where('precio_venta', '>=', $request->precio_min);
            }

            if ($request->filled('precio_max')) {
                $query->where('precio_venta', '<=', $request->precio_max);
            }

            if ($request->boolean('solo_ofertas')) {
                $query->whereNotNull('precio_oferta');
            }

            match ($request->get('sort', 'nombre_asc')) {
                'precio_asc'  => $query->orderBy('precio_venta', 'asc'),
                'precio_desc' => $query->orderBy('precio_venta', 'desc'),
                'nuevos'      => $query->orderBy('created_at', 'desc'),
                default       => $query->orderBy('nombre', 'asc'),
            };

            $productos = $query->paginate($request->get('per_page', 20));

            $productos->getCollection()->transform(fn($p) => $this->formatearProducto($p));

            return response()->json([
                'success'   => true,
                'productos' => $productos,
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al obtener productos'], 500);
        }
    }

    /**
     * Detalle de un producto.
     * GET /tienda/productos/{id}
     */
    public function show(string $id)
    {
        try {
            $producto = Producto::with([
                'categorias:id,nombre',
                'imagenes' => fn($q) => $q->select([
                    'id', 'producto_id', 'url', 'url_thumb', 'url_medium', 'es_principal', 'orden',
                ])->orderBy('orden'),
            ])
            ->where('estado', 'activo')
            ->find($id);

            if (!$producto) {
                return response()->json(['success' => false, 'message' => 'Producto no encontrado'], 404);
            }

            return response()->json([
                'success'  => true,
                'producto' => $this->formatearProducto($producto, detalle: true),
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al obtener producto'], 500);
        }
    }

    /**
     * Búsqueda rápida (para autocomplete / barra de búsqueda).
     * GET /tienda/productos/buscar?q=texto
     */
    public function buscar(Request $request)
    {
        $q = $request->get('q', '');

        if (strlen($q) < 2) {
            return response()->json(['success' => true, 'productos' => []]);
        }

        try {
            $productos = Producto::with(['imagenPrincipal' => fn($q) => $q->select([
                'id', 'producto_id', 'url_thumb', 'es_principal',
            ])])
            ->where('estado', 'activo')
            ->where(function ($query) use ($q) {
                $query->where('nombre', 'LIKE', "%{$q}%")
                      ->orWhere('marca', 'LIKE', "%{$q}%");
            })
            ->orderBy('nombre')
            ->limit(10)
            ->get();

            return response()->json([
                'success'   => true,
                'productos' => $productos->map(fn($p) => [
                    'id'           => $p->id,
                    'nombre'       => $p->nombre,
                    'marca'        => $p->marca,
                    'precio_final' => $p->precio_final,
                    'en_oferta'    => !is_null($p->precio_oferta),
                    'disponible'   => $p->stock > 0,
                    'imagen'       => $p->imagenPrincipal?->url_thumb,
                ]),
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error en la búsqueda'], 500);
        }
    }

    /**
     * Productos destacados para el home (los más vendidos activos).
     * GET /tienda/productos/destacados
     */
    public function destacados()
    {
        try {
            $productos = Producto::with(['imagenPrincipal' => fn($q) => $q->select([
                'id', 'producto_id', 'url', 'url_thumb', 'es_principal',
            ])])
            ->where('estado', 'activo')
            ->withCount(['ventaDetalles as veces_vendido'])
            ->orderBy('veces_vendido', 'desc')
            ->limit(8)
            ->get();

            return response()->json([
                'success'   => true,
                'productos' => $productos->map(fn($p) => $this->formatearProducto($p)),
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al obtener destacados'], 500);
        }
    }

    /**
     * Productos en oferta.
     * GET /tienda/productos/ofertas
     */
    public function ofertas(Request $request)
    {
        try {
            $productos = Producto::with(['imagenPrincipal' => fn($q) => $q->select([
                'id', 'producto_id', 'url', 'url_thumb', 'es_principal',
            ])])
            ->where('estado', 'activo')
            ->whereNotNull('precio_oferta')
            ->orderBy('nombre')
            ->paginate($request->get('per_page', 20));

            $productos->getCollection()->transform(fn($p) => $this->formatearProducto($p));

            return response()->json([
                'success'   => true,
                'productos' => $productos,
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al obtener ofertas'], 500);
        }
    }

    /**
     * Formatea un producto para respuesta pública.
     * Nunca expone campos internos.
     */
    private function formatearProducto(Producto $producto, bool $detalle = false): array
    {
        $data = [
            'id'           => $producto->id,
            'nombre'       => $producto->nombre,
            'marca'        => $producto->marca,
            'color'        => $producto->color,
            'precio_venta' => (float) $producto->precio_venta,
            'precio_oferta'=> $producto->precio_oferta ? (float) $producto->precio_oferta : null,
            'precio_final' => (float) $producto->precio_final,
            'en_oferta'    => !is_null($producto->precio_oferta),
            'disponible'   => $producto->stock > 0,
            'garantia'     => $producto->garantia,
            'categorias'   => $producto->relationLoaded('categorias')
                ? $producto->categorias->map(fn($c) => ['id' => $c->id, 'nombre' => $c->nombre])
                : [],
            'imagen_principal' => $this->imagenPrincipal($producto),
            'imagenes'     => $producto->relationLoaded('imagenes')
                ? $producto->imagenes->map(fn($i) => [
                    'id'          => $i->id,
                    'url'         => $i->url,
                    'url_thumb'   => $i->url_thumb,
                    'url_medium'  => $i->url_medium,
                    'es_principal'=> (bool) $i->es_principal,
                ])
                : [],
        ];

        // Campos extra solo en detalle de producto
        if ($detalle) {
            $data['descripcion']      = $producto->descripcion;
            $data['especificaciones'] = $producto->especificaciones;
        }

        return $data;
    }

    private function imagenPrincipal(Producto $producto): ?string
    {
        if ($producto->relationLoaded('imagenPrincipal') && $producto->imagenPrincipal) {
            $img = $producto->imagenPrincipal;
            return $img->url_thumb ?? $img->url_medium ?? $img->url;
        }

        if ($producto->relationLoaded('imagenes') && $producto->imagenes->isNotEmpty()) {
            $principal = $producto->imagenes->firstWhere('es_principal', true)
                      ?? $producto->imagenes->first();
            return $principal->url_thumb ?? $principal->url_medium ?? $principal->url;
        }

        return null;
    }
}
