<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MovimientoStockController;
use App\Http\Controllers\CursoController;
use App\Http\Controllers\ModuloController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\TiendaController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PersonalController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentController;

// ARCHIVOS PÚBLICOS
Route::get('/media/{path}', [MediaController::class, 'show'])->where('path', '.*');

// AUTENTICACIÓN
Route::get('/auth/admin/status', [AuthController::class, 'adminStatus']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/admin/setup', [AuthController::class, 'setupAdmin']);
Route::post('/auth/admin/login', [AuthController::class, 'adminLogin']);
Route::post('/auth/cliente/registro', [AuthController::class, 'clientRegister']);
Route::post('/auth/cliente/login', [AuthController::class, 'clientLogin']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::put('/auth/password', [AuthController::class, 'updatePassword']);

    // NOTIFICACIONES DEL USUARIO AUTENTICADO
    Route::get('/notificaciones', [NotificationController::class, 'index']);
    Route::put('/notificaciones/leer-todas', [NotificationController::class, 'markAll']);
    Route::put('/notificaciones/{id}/leer', [NotificationController::class, 'markRead']);

    // Comprobantes privados: el controlador verifica si el usuario es
    // personal autorizado o el cliente dueño del pedido.
    Route::get('/pagos/{id}/comprobante', [PaymentController::class, 'proof']);

    Route::middleware('role:cliente')->group(function () {
        Route::put('/auth/cliente/perfil', [AuthController::class, 'updateClientProfile']);
        Route::get('/auth/cliente/pedidos', [AuthController::class, 'clientOrders']);
        Route::get('/auth/cliente/pagos', [PaymentController::class, 'clientIndex']);
        Route::post('/auth/cliente/pedidos/{pedidoId}/pago', [PaymentController::class, 'report']);
    });
});

// CATÁLOGO / TIENDA PÚBLICA
Route::get('/tienda/config', [CatalogoController::class, 'show']);
Route::get('/tienda/productos', [TiendaController::class, 'catalog']);
Route::get('/tienda/productos/{slugOrId}', [TiendaController::class, 'product']);
Route::get('/tienda/categorias', [TiendaController::class, 'categories']);
Route::post('/tienda/pedidos', [TiendaController::class, 'store']);
Route::get('/tienda/seguimiento/{codigo}', [TiendaController::class, 'tracking']);

// CAPACITACIÓN PÚBLICA
Route::get('/capacitacion', [CursoController::class, 'listarPublicos']);
Route::get('/capacitacion/{id}', [CursoController::class, 'showPublico']);

// Alias de compatibilidad con versiones anteriores
Route::get('/cursos', [CursoController::class, 'listarPublicos']);
Route::get('/cursos/{id}', [CursoController::class, 'showPublico']);

// PANEL ADMINISTRATIVO: todo requiere login
Route::middleware(['auth:sanctum', 'role:admin,trabajador,caja,almacen'])->group(function () {
    Route::get('/dashboard/totales', [DashboardController::class, 'totales']);

    Route::get('/productos', [ProductoController::class, 'index']);
    Route::get('/productos/{id}', [ProductoController::class, 'show']);
    Route::get('/categorias', [CategoriaController::class, 'listar']);

    Route::get('/pedidos', [PedidoController::class, 'index']);
    Route::get('/pedidos/{id}', [PedidoController::class, 'show']);
    Route::get('/pedidos/{id}/evidencia-entrega', [PedidoController::class, 'deliveryProof']);

    Route::get('/catalogo/config', [CatalogoController::class, 'show']);
});

// CATÁLOGO + INVENTARIO: administrador y almacén
Route::middleware(['auth:sanctum', 'role:admin,trabajador,almacen'])->group(function () {
    Route::post('/productos', [ProductoController::class, 'store']);
    Route::match(['post', 'put'], '/productos/{id}', [ProductoController::class, 'update']);
    Route::delete('/productos/{id}', [ProductoController::class, 'destroy']);
    Route::put('/productos/{id}/imagenes/{imagenId}/principal', [ProductoController::class, 'setMainImage']);
    Route::delete('/productos/{id}/imagenes/{imagenId}', [ProductoController::class, 'deleteImage']);

    Route::post('/movimientos-stock', [MovimientoStockController::class, 'store']);
    Route::get('/productos/{id}/movimientos-stock', [MovimientoStockController::class, 'byProduct']);

    Route::post('/categorias', [CategoriaController::class, 'registrarCategoria']);
    Route::put('/categorias/{id}', [CategoriaController::class, 'actualizar']);
    Route::delete('/categorias/{id}', [CategoriaController::class, 'borrar']);

    Route::get('/compras', [CompraController::class, 'index']);
    Route::post('/compras', [CompraController::class, 'store']);
    Route::get('/compras/{id}', [CompraController::class, 'show']);
    Route::put('/compras/{id}/estado', [CompraController::class, 'updateStatus']);
    Route::get('/compras/{id}/pdf', [CompraController::class, 'pdf']);
});

// VENTAS / CLIENTES: administrador y caja
Route::middleware(['auth:sanctum', 'role:admin,trabajador,caja'])->group(function () {
    Route::post('/pedidos', [PedidoController::class, 'store']);
    Route::get('/pedidos/{id}/pdf', [PedidoController::class, 'pdf']);

    Route::get('/usuarios', [UsuarioController::class, 'listar']);
    Route::post('/usuarios', [UsuarioController::class, 'registrarUsuario']);
    Route::get('/usuarios/{id}', [UsuarioController::class, 'show']);
    Route::put('/usuarios/{id}', [UsuarioController::class, 'actualizar']);
    Route::delete('/usuarios/{id}', [UsuarioController::class, 'borrar']);
});

// ESTADOS DE PEDIDOS ONLINE: caja, almacén o admin
Route::middleware(['auth:sanctum', 'role:admin,trabajador,caja,almacen'])->group(function () {
    Route::match(['post', 'put'], '/pedidos/{id}', [PedidoController::class, 'update']);
});


// PAGOS: administrador, trabajador o caja
Route::middleware(['auth:sanctum', 'role:admin,trabajador,caja'])->group(function () {
    Route::get('/pagos', [PaymentController::class, 'index']);
    Route::put('/pagos/{id}/estado', [PaymentController::class, 'updateStatus']);
});

// PERSONALIZAR TIENDA + GESTIÓN DE PERSONAL: solo administrador
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::put('/catalogo/config', [CatalogoController::class, 'update']);

    Route::get('/personal', [PersonalController::class, 'index']);
    Route::post('/personal', [PersonalController::class, 'store']);
    Route::get('/personal/{id}', [PersonalController::class, 'show']);
    Route::put('/personal/{id}', [PersonalController::class, 'update']);
    Route::put('/personal/{id}/estado', [PersonalController::class, 'toggle']);
});

// CAPACITACIÓN ADMIN: CRUD dinámico, módulos y relación con productos
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::get('/admin/cursos', [CursoController::class, 'adminIndex']);
    Route::get('/admin/cursos/{id}', [CursoController::class, 'adminShow']);
    Route::post('/admin/cursos', [CursoController::class, 'registrar']);
    Route::match(['post', 'put'], '/admin/cursos/{id}', [CursoController::class, 'actualizar']);
    Route::delete('/admin/cursos/{id}', [CursoController::class, 'borrar']);

    Route::post('/admin/modulos', [ModuloController::class, 'registrar']);
    Route::get('/admin/modulos/{id}', [ModuloController::class, 'show']);
    Route::put('/admin/modulos/{id}', [ModuloController::class, 'actualizar']);
    Route::delete('/admin/modulos/{id}', [ModuloController::class, 'borrar']);
    Route::put('/admin/cursos/{id}/modulos/reordenar', [ModuloController::class, 'reordenar']);

    // Alias de compatibilidad
    Route::post('/cursos', [CursoController::class, 'registrar']);
    Route::put('/cursos/{id}', [CursoController::class, 'actualizar']);
    Route::delete('/cursos/{id}', [CursoController::class, 'borrar']);
    Route::post('/modulos', [ModuloController::class, 'registrar']);
    Route::get('/modulos/{id}', [ModuloController::class, 'show']);
    Route::put('/modulos/{id}', [ModuloController::class, 'actualizar']);
    Route::delete('/modulos/{id}', [ModuloController::class, 'borrar']);
});

Route::get('/user', fn (Request $request) => $request->user())->middleware('auth:sanctum');
