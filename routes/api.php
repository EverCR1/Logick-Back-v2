<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ServicioController;
use App\Http\Controllers\CreditoController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\VentaController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\SucursalController;
use App\Http\Controllers\AdminPedidoController;
use App\Http\Controllers\AdminResenaController;
use App\Http\Controllers\AdminCuponController;
use App\Http\Controllers\CuentaPuntosController;
use App\Http\Controllers\Tienda\ProductoController        as TiendaProductoController;
use App\Http\Controllers\Tienda\CategoriaController       as TiendaCategoriaController;
use App\Http\Controllers\Tienda\PedidoController          as TiendaPedidoController;
use App\Http\Controllers\Tienda\CuentaAuthController      as TiendaCuentaAuthController;
use App\Http\Controllers\Tienda\CuentaDireccionController as TiendaCuentaDireccionController;
use App\Http\Controllers\Tienda\CuentaFavoritoController  as TiendaCuentaFavoritoController;
use App\Http\Controllers\Tienda\CuentaPedidoController    as TiendaCuentaPedidoController;
use App\Http\Controllers\Tienda\CuponController           as TiendaCuponController;
use App\Http\Controllers\Tienda\ResenaController          as TiendaResenaController;
use App\Http\Controllers\Tienda\PreguntaController        as TiendaPreguntaController;

/*
|--------------------------------------------------------------------------
| API Routes — Logick-Back v2 (Laravel 11 / PHP 8.3)
|--------------------------------------------------------------------------
*/

// ── Tienda: rutas públicas ────────────────────────────────────────────────
Route::prefix('tienda')->group(function () {

    // Catálogo
    Route::get('/categorias',            [TiendaCategoriaController::class, 'tree']);
    Route::get('/categorias/buscar',     [TiendaCategoriaController::class, 'buscar']);
    Route::get('/productos',             [TiendaProductoController::class, 'index']);
    Route::get('/productos/buscar',      [TiendaProductoController::class, 'buscar']);
    Route::get('/productos/destacados',  [TiendaProductoController::class, 'destacados']);
    Route::get('/productos/ofertas',     [TiendaProductoController::class, 'ofertas']);
    Route::get('/productos/{id}',        [TiendaProductoController::class, 'show']);

    // Reseñas y preguntas (públicas)
    Route::get('/productos/{id}/resenas',   [TiendaResenaController::class,  'index']);
    Route::get('/productos/{id}/preguntas', [TiendaPreguntaController::class, 'index']);

    // Reseñas y preguntas (requieren cuenta)
    Route::middleware('auth.cuenta')->group(function () {
        Route::get('/productos/{id}/resenas/mia', [TiendaResenaController::class,  'mia']);
        Route::post('/productos/{id}/resenas',    [TiendaResenaController::class,  'store']);
        Route::post('/productos/{id}/preguntas',  [TiendaPreguntaController::class, 'store']);
    });

    // Pedidos (invitados y cuentas)
    Route::post('/pedidos',                      [TiendaPedidoController::class, 'store']);
    Route::get('/pedidos/{numero}',              [TiendaPedidoController::class, 'show']);
    Route::post('/pedidos/{numero}/comprobante', [TiendaPedidoController::class, 'subirComprobante']);

    // Auth de cuentas (público)
    Route::prefix('auth')->group(function () {
        Route::post('/registro',          [TiendaCuentaAuthController::class, 'registro']);
        Route::post('/login',             [TiendaCuentaAuthController::class, 'login']);
        Route::get('/google',             [TiendaCuentaAuthController::class, 'googleRedirect']);
        Route::get('/google/callback',    [TiendaCuentaAuthController::class, 'googleCallback']);
    });

    // Cupones (público — token opcional para validaciones extra)
    Route::post('/cupones/validar', [TiendaCuponController::class, 'validar']);

    // Auth de cuentas (protegido — requiere token de Cuenta)
    Route::prefix('auth')->middleware('auth.cuenta')->group(function () {
        Route::post('/logout',   [TiendaCuentaAuthController::class, 'logout']);
        Route::get('/me',        [TiendaCuentaAuthController::class, 'me']);
        Route::put('/perfil',    [TiendaCuentaAuthController::class, 'actualizarPerfil']);
        Route::put('/password',  [TiendaCuentaAuthController::class, 'cambiarPassword']);
    });

    // Cuenta (protegido) — direcciones, favoritos, pedidos
    Route::prefix('cuenta')->middleware('auth.cuenta')->group(function () {

        // Pedidos
        Route::get('/pedidos',          [TiendaCuentaPedidoController::class, 'index']);
        Route::get('/pedidos/{numero}', [TiendaCuentaPedidoController::class, 'show']);

        // Direcciones
        Route::get('/direcciones',                    [TiendaCuentaDireccionController::class, 'index']);
        Route::post('/direcciones',                   [TiendaCuentaDireccionController::class, 'store']);
        Route::put('/direcciones/{id}',               [TiendaCuentaDireccionController::class, 'update']);
        Route::delete('/direcciones/{id}',            [TiendaCuentaDireccionController::class, 'destroy']);
        Route::put('/direcciones/{id}/principal',     [TiendaCuentaDireccionController::class, 'marcarPrincipal']);

        // Favoritos
        Route::get('/favoritos',              [TiendaCuentaFavoritoController::class, 'index']);
        Route::post('/favoritos',             [TiendaCuentaFavoritoController::class, 'store']);
        Route::delete('/favoritos/{id}',      [TiendaCuentaFavoritoController::class, 'destroy']);

        // Cupones de la cuenta
        Route::get('/cupones', [TiendaCuponController::class, 'misCupones']);

        // Puntos
        Route::get('/puntos',          [CuentaPuntosController::class, 'index']);
        Route::post('/puntos/canjear', [CuentaPuntosController::class, 'canjear']);
    });
});

// Rutas públicas
Route::post('/login',    [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register'])->middleware('auth:sanctum', 'role:administrador');

// Recuperación de contraseña
Route::post('/password/forgot', [PasswordResetController::class, 'forgotPassword']);
Route::post('/password/reset',  [PasswordResetController::class, 'resetPassword']);

// Rutas protegidas
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/logout',          [AuthController::class, 'logout']);
    Route::get('/profile',          [AuthController::class, 'profile']);
    Route::put('/profile',          [AuthController::class, 'updateProfile']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    // Usuarios (solo administrador)
    Route::middleware('role:administrador')->group(function () {
        Route::apiResource('users', UserController::class);
        Route::post('/users/{id}/change-status', [UserController::class, 'changeStatus']);
    });

    // Sucursales
    Route::get('/sucursales/activas', [SucursalController::class, 'activas']);
    Route::middleware('role:administrador')->group(function () {
        Route::apiResource('sucursales', SucursalController::class);
        Route::post('/sucursales/{id}/change-status', [SucursalController::class, 'changeStatus']);
    });

    // Proveedores
    Route::middleware('role:administrador,vendedor')->group(function () {
        Route::apiResource('proveedores', ProveedorController::class);
        Route::post('/proveedores/{id}/change-status', [ProveedorController::class, 'changeStatus']);
        Route::get('/proveedores/activos',             [ProveedorController::class, 'activos']);
        Route::get('/proveedores/search',              [ProveedorController::class, 'search']);
    });

    // Categorías (lectura libre, escritura sólo admin/vendedor)
    Route::get('/categorias-tree',             [CategoriaController::class, 'tree']);
    Route::get('/categorias-flat',             [CategoriaController::class, 'flat']);
    Route::get('/categorias-by-level/{level}', [CategoriaController::class, 'byLevel']);

    Route::middleware('role:administrador,vendedor')->group(function () {
        Route::apiResource('categorias', CategoriaController::class);
        Route::post('/categorias/{id}/change-status', [CategoriaController::class, 'changeStatus']);
        Route::post('/categorias/{id}/upload-image',  [CategoriaController::class, 'uploadImage']);
        Route::delete('/categorias/{id}/imagen',      [CategoriaController::class, 'deleteImage']);
    });

    // Productos
    Route::get('/productos/stock-bajo', [ProductoController::class, 'stockBajo']);
    Route::get('/productos/search',     [ProductoController::class, 'search']);

    Route::middleware('role:administrador,vendedor')->group(function () {
        Route::apiResource('productos', ProductoController::class);
        Route::post('/productos/{id}/change-status',              [ProductoController::class, 'changeStatus']);
        Route::post('/productos/{id}/upload-images',              [ProductoController::class, 'uploadImages']);
        Route::post('/productos/{id}/images/{imagenId}/set-main', [ProductoController::class, 'setImagenPrincipal']);
        Route::delete('/productos/{id}/images/{imagenId}',        [ProductoController::class, 'deleteImage']);
        Route::post('/productos/{id}/reorder-images',             [ProductoController::class, 'reorderImages']);
    });

    // Servicios
    Route::get('/servicios/search', [ServicioController::class, 'search']);

    Route::middleware('role:administrador,vendedor')->group(function () {
        Route::apiResource('servicios', ServicioController::class);
        Route::post('/servicios/{id}/change-status',       [ServicioController::class, 'changeStatus']);
        Route::post('/servicios/{id}/upload-image',        [ServicioController::class, 'uploadImage']);
        Route::delete('/servicios/{id}/images/{imagenId}', [ServicioController::class, 'deleteImage']);
    });

    // Clientes
    Route::get('/clientes/search', [ClienteController::class, 'search']);

    Route::middleware('role:administrador,vendedor')->group(function () {
        // Rutas estáticas ANTES de apiResource para evitar que /{id} las capture
        Route::get('/clientes/frecuentes',    [ClienteController::class, 'frecuentes']);
        Route::get('/clientes/estadisticas',  [ClienteController::class, 'estadisticas']);
        Route::post('/clientes/crear-rapido', [ClienteController::class, 'crearRapido']);
        Route::post('/clientes/todos',        [ClienteController::class, 'todos']);

        Route::apiResource('clientes', ClienteController::class);
        Route::post('/clientes/{id}/change-status', [ClienteController::class, 'changeStatus']);
    });

    // Créditos
    Route::get('/creditos/search', [CreditoController::class, 'search']);

    Route::middleware('role:administrador,vendedor')->group(function () {
        Route::apiResource('creditos', CreditoController::class);
        Route::post('/creditos/{id}/change-status',  [CreditoController::class, 'changeStatus']);
        Route::post('/creditos/{id}/registrar-pago', [CreditoController::class, 'registrarPago']);
        Route::get('/creditos/estado/{estado}',      [CreditoController::class, 'byEstado']);
    });

    // Ventas
    Route::get('/ventas/search', [VentaController::class, 'search']);

    Route::middleware('role:administrador,vendedor')->group(function () {
        Route::apiResource('ventas', VentaController::class);
        Route::post('/ventas/{id}/cancelar',               [VentaController::class, 'cancelar']);
        Route::get('/ventas/buscar/productos',             [VentaController::class, 'buscarProductos']);
        Route::get('/ventas/buscar/servicios',             [VentaController::class, 'buscarServicios']);
        Route::get('/ventas/buscar/clientes',              [VentaController::class, 'buscarClientes']);
        Route::get('/ventas/reporte',                      [VentaController::class, 'reporte']);
        Route::get('/ventas/ultimos-30-dias',              [VentaController::class, 'ultimos30Dias']);
        Route::get('/ventas/por-rango',                    [VentaController::class, 'ventasPorRango']);
        Route::get('/ventas/{id}/detalles',                [VentaController::class, 'detalles']);
        Route::post('/ventas/{id}/agregar-detalle',        [VentaController::class, 'agregarDetalle']);
        Route::delete('/ventas/{id}/detalles/{detalleId}', [VentaController::class, 'eliminarDetalle']);
    });

    // Auditoría (solo administrador)
    Route::middleware('role:administrador')->group(function () {
        Route::get('auditoria',              [AuditoriaController::class, 'index']);
        Route::get('auditoria/estadisticas', [AuditoriaController::class, 'estadisticas']);
        Route::get('auditoria/modulos',      [AuditoriaController::class, 'modulos']);
        Route::get('auditoria/{id}',         [AuditoriaController::class, 'show']);
    });

    // Pedidos tienda (admin/vendedor)
    Route::middleware('role:administrador,vendedor')->prefix('pedidos-tienda')->group(function () {
        Route::get('/estadisticas',   [AdminPedidoController::class, 'estadisticas']);
        Route::get('/',               [AdminPedidoController::class, 'index']);
        Route::get('/{id}',           [AdminPedidoController::class, 'show']);
        Route::patch('/{id}/estado',  [AdminPedidoController::class, 'cambiarEstado']);
    });

    // Cupones (solo administrador)
    Route::middleware('role:administrador')->prefix('admin/cupones')->group(function () {
        Route::get('/',               [AdminCuponController::class, 'index']);
        Route::post('/',              [AdminCuponController::class, 'store']);
        Route::get('/{id}',           [AdminCuponController::class, 'show']);
        Route::put('/{id}',           [AdminCuponController::class, 'update']);
        Route::delete('/{id}',        [AdminCuponController::class, 'destroy']);
        Route::patch('/{id}/estado',  [AdminCuponController::class, 'toggleEstado']);
    });

    // Reseñas y preguntas (admin/vendedor)
    Route::middleware('role:administrador,vendedor')->prefix('admin')->group(function () {
        Route::get('/resenas',               [AdminResenaController::class, 'index']);
        Route::patch('/resenas/{id}/estado', [AdminResenaController::class, 'cambiarEstado']);
        Route::get('/preguntas',             [AdminResenaController::class, 'preguntasIndex']);
        Route::patch('/preguntas/{id}',      [AdminResenaController::class, 'preguntaUpdate']);
    });

    // Reportes (solo administrador)
    Route::middleware('role:administrador')->prefix('reportes')->name('reportes.')->group(function () {
        Route::get('/resumen',                  [ReporteController::class, 'resumen'])->name('resumen');
        Route::get('/ventas',                   [ReporteController::class, 'ventas'])->name('ventas');
        Route::get('/productos-mas-vendidos',   [ReporteController::class, 'productosMasVendidos'])->name('productos-mas-vendidos');
        Route::get('/inventario',               [ReporteController::class, 'inventario'])->name('inventario');
        Route::get('/top-clientes',             [ReporteController::class, 'topClientes'])->name('top-clientes');
        Route::get('/rendimiento-vendedores',   [ReporteController::class, 'rendimientoVendedores'])->name('rendimiento-vendedores');
        Route::get('/servicios-mas-realizados', [ReporteController::class, 'serviciosMasRealizados'])->name('servicios-mas-realizados');
        Route::get('/sucursales',               [ReporteController::class, 'sucursales'])->name('sucursales');
        Route::get('/ganancias',                [ReporteController::class, 'ganancias'])->name('ganancias');
        Route::get('/tienda-pedidos',           [ReporteController::class, 'tiendaPedidos'])->name('tienda-pedidos');
        Route::post('/exportar',                [ReporteController::class, 'exportar'])->name('exportar');
    });

});
