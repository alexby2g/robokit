<?php
// COPIAR estas rutas dentro de routes/api.php del backend existente.

use App\Http\Controllers\CompraController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\MovimientoStockController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\TiendaController;
use Illuminate\Support\Facades\Route;


// Media publica robusta: no depende de public/storage ni de storage:link.
Route::get('/media/{path}', [MediaController::class, 'show'])->where('path', '.*');

Route::get('/dashboard/totales', [DashboardController::class, 'totales']);

Route::get('/compras', [CompraController::class, 'index']);
Route::post('/compras', [CompraController::class, 'store']);
Route::get('/compras/{id}', [CompraController::class, 'show']);
Route::get('/compras/{id}/pdf', [CompraController::class, 'pdf']);

Route::get('/pedidos', [PedidoController::class, 'index']);
Route::post('/pedidos', [PedidoController::class, 'store']);
Route::get('/pedidos/{id}', [PedidoController::class, 'show']);
Route::put('/pedidos/{id}', [PedidoController::class, 'update']);
Route::get('/pedidos/{id}/pdf', [PedidoController::class, 'pdf']);

Route::post('/tienda/pedidos', [TiendaController::class, 'store']);

// Si tu routes/api.php ya contiene estas rutas, reemplaza las existentes (no las dupliques).
Route::get('/productos', [ProductoController::class, 'index']);
Route::post('/productos', [ProductoController::class, 'store']);
Route::get('/productos/{id}', [ProductoController::class, 'show']);
Route::put('/productos/{id}', [ProductoController::class, 'update']);
Route::delete('/productos/{id}', [ProductoController::class, 'destroy']);

Route::post('/movimientos-stock', [MovimientoStockController::class, 'store']);
Route::get('/productos/{id}/movimientos-stock', [MovimientoStockController::class, 'byProduct']);
