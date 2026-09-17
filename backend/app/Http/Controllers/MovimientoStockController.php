<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class MovimientoStockController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'id_producto' => ['required', 'integer'],
            'tipo' => ['required', 'in:entrada,salida'],
            'cantidad' => ['required', 'integer', 'min:1'],
            'motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $id = DB::transaction(function () use ($data) {
            $producto = DB::table('producto')->where('id', $data['id_producto'])->lockForUpdate()->first();
            abort_if(!$producto, 404, 'Producto no encontrado.');
            $anterior = (int) $producto->Stock;
            $reservado = Schema::hasColumn('producto', 'stock_reservado') ? (int) ($producto->stock_reservado ?? 0) : 0;
            $cantidad = (int) $data['cantidad'];

            if ($data['tipo'] === 'salida') {
                $disponible = max(0, $anterior - $reservado);
                if ($cantidad > $disponible) {
                    throw ValidationException::withMessages(['cantidad' => "Stock disponible insuficiente. Disponible: {$disponible}; reservado: {$reservado}."]);
                }
            }

            $nuevo = $data['tipo'] === 'entrada' ? $anterior + $cantidad : $anterior - $cantidad;
            DB::table('producto')->where('id', $producto->id)->update(['Stock' => $nuevo]);

            return (int) DB::table('movimiento_stock')->insertGetId([
                'id_producto' => $producto->id,
                'tipo' => $data['tipo'],
                'cantidad' => $cantidad,
                'stock_anterior' => $anterior,
                'stock_nuevo' => $nuevo,
                'motivo' => $data['motivo'] ?? 'Ajuste manual',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
        return response()->json(['message' => 'Stock actualizado.', 'id' => $id], 201);
    }

    public function byProduct(int $id)
    {
        $rows = DB::table('movimiento_stock')->where('id_producto', $id)->orderByDesc('id')->get();
        return response()->json(['movimientos' => $rows]);
    }
}
