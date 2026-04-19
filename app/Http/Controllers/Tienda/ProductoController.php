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
                $this->aplicarBusqueda($query, $request->search, $request->boolean('search_desc'));
            }

            if ($request->filled('categoria_id')) {
                $ids = array_filter(array_map('intval', explode(',', $request->categoria_id)));
                $query->whereHas('categorias', fn($q) => $q->whereIn('categorias.id', $ids));
            }

            if ($request->filled('marca')) {
                $marcas = array_filter(array_map('trim', explode(',', $request->marca)));
                count($marcas) === 1
                    ? $query->where('marca', $marcas[0])
                    : $query->whereIn('marca', $marcas);
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

            if ($request->filled('stock')) {
                match ($request->stock) {
                    'disponible' => $query->where('stock', '>', 0),
                    'agotado'    => $query->where('stock', '<=', 0),
                    default      => null,
                };
            }

            $query->orderByRaw('(stock > 0) DESC');

            match ($request->get('sort', 'nombre_asc')) {
                'precio_asc'    => $query->orderBy('precio_venta', 'asc'),
                'precio_desc'   => $query->orderBy('precio_venta', 'desc'),
                'nuevos'        => $query->orderBy('created_at', 'desc'),
                'nombre_desc'   => $query->orderBy('nombre', 'desc'),
                'mejor_rating'  => $query
                    ->withAvg(['resenas' => fn($q) => $q->where('estado', 'publicado')], 'rating')
                    ->orderByRaw('resenas_avg_rating IS NULL ASC, resenas_avg_rating DESC')
                    ->orderBy('nombre', 'asc'),
                default         => $query->orderBy('nombre', 'asc'),
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
                'atributos',
            ])
            ->where('estado', 'activo')
            ->find($id);

            if (!$producto) {
                return response()->json(['success' => false, 'message' => 'Producto no encontrado'], 404);
            }

            // Variantes del mismo grupo
            $variantes = [];
            if ($producto->grupo_variante) {
                $variantes = Producto::with([
                    'imagenPrincipal' => fn($q) => $q->select([
                        'id', 'producto_id', 'url_thumb', 'url_medium', 'url', 'es_principal',
                    ]),
                    'atributos',
                ])
                ->where('estado', 'activo')
                ->where('grupo_variante', $producto->grupo_variante)
                ->where('id', '!=', $producto->id)
                ->get()
                ->map(fn($v) => [
                    'id'               => $v->id,
                    'color'            => $v->color,
                    'atributos'        => $v->atributos->map(fn($a) => ['nombre' => $a->nombre, 'valor' => $a->valor])->values()->all(),
                    'precio_venta'     => (float) $v->precio_venta,
                    'precio_oferta'    => $v->precio_oferta ? (float) $v->precio_oferta : null,
                    'en_oferta'        => !is_null($v->precio_oferta),
                    'imagen_principal' => $this->imagenPrincipal($v),
                ])
                ->values()
                ->all();
            }

            return response()->json([
                'success'  => true,
                'producto' => $this->formatearProducto($producto, true, $variantes),
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
            $builder = Producto::with(['imagenes' => fn($q) => $q->select([
                'id', 'producto_id', 'url', 'url_thumb', 'url_medium', 'es_principal', 'orden',
            ])->orderBy('orden')])
            ->where('estado', 'activo');

            $this->aplicarBusqueda($builder, $q);

            $productos = $builder->orderBy('nombre')->limit(10)->get();

            return response()->json([
                'success'   => true,
                'productos' => $productos->map(fn($p) => [
                    'id'           => $p->id,
                    'nombre'       => $p->nombre,
                    'marca'        => $p->marca,
                    'precio_final' => $p->precio_final,
                    'en_oferta'    => !is_null($p->precio_oferta),
                    'disponible'   => $p->stock > 0,
                    'imagen_principal' => $this->imagenPrincipal($p),
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
            $productos = Producto::with(['imagenes' => fn($q) => $q->select([
                'id', 'producto_id', 'url', 'url_thumb', 'url_medium', 'es_principal', 'orden',
            ])->orderBy('orden')])
            ->where('estado', 'activo')
            ->withCount(['ventaDetalles as veces_vendido'])
            ->orderByRaw('(stock > 0) DESC')
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
            $productos = Producto::with(['imagenes' => fn($q) => $q->select([
                'id', 'producto_id', 'url', 'url_thumb', 'url_medium', 'es_principal', 'orden',
            ])->orderBy('orden')])
            ->where('estado', 'activo')
            ->whereNotNull('precio_oferta')
            ->orderByRaw('(stock > 0) DESC')
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
     * Aplica búsqueda multi-palabra sobre los campos públicos.
     * Cada palabra debe coincidir en al menos un campo (AND entre palabras, OR entre campos).
     * Ej: "Monitor Negro" → encuentra productos donde "Monitor" aparece en algún campo
     *     Y "Negro" aparece en algún campo.
     */
    /**
     * Aplica búsqueda multi-palabra sobre los campos públicos.
     * Cada palabra debe coincidir en al menos un campo (AND entre palabras, OR entre campos).
     *
     * Manejo de género en español: si la palabra termina en 'o' o 'a' y tiene
     * 4+ caracteres, busca por la raíz sin la vocal final para que "Rojo" y "Roja"
     * ambos encuentren el mismo producto independientemente de cómo esté guardado.
     * Ej: "roja" → raíz "roj" → LIKE "%roj%" → coincide con "Rojo", "Roja", "Rojos", "Rojas"
     */
    private function aplicarBusqueda($query, string $texto, bool $conDescripcion = false): void
    {
        $palabras = array_filter(explode(' ', preg_replace('/\s+/', ' ', trim($texto))));

        foreach ($palabras as $palabra) {
            $termino = mb_strtolower($palabra);

            // Si termina en vocal de género (o/a) y tiene suficiente longitud, usar raíz
            if (mb_strlen($termino) >= 4 && preg_match('/[oa]$/i', $termino)) {
                $termino = mb_substr($termino, 0, -1); // "rojo" → "roj", "roja" → "roj"
            }

            $like = "%{$termino}%";
            $query->where(function ($q) use ($like, $conDescripcion) {
                $q->where('nombre', 'LIKE', $like)
                  ->orWhere('marca', 'LIKE', $like)
                  ->orWhere('color', 'LIKE', $like);
                if ($conDescripcion) {
                    $q->orWhere('descripcion', 'LIKE', $like);
                }
            });
        }
    }

    /**
     * Formatea un producto para respuesta pública.
     * Nunca expone campos internos.
     */
    private function formatearProducto(Producto $producto, bool $detalle = false, array $variantes = []): array
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
            'stock'        => (int) $producto->stock,
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
            $data['grupo_variante']   = $producto->grupo_variante;
            $data['atributos']        = $producto->relationLoaded('atributos')
                ? $producto->atributos->map(fn($a) => ['nombre' => $a->nombre, 'valor' => $a->valor])->values()->all()
                : [];
            $data['variantes']        = $variantes;
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
