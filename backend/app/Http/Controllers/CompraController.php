<?php

namespace App\Http\Controllers;

use App\Services\SimplePdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompraController extends Controller
{
    public function index()
    {
        $rows = DB::table('compra')->orderByDesc('id')->get()->each(function ($row) { $row->total = $row->Total; $row->fecha = $row->Fecha; });
        return response()->json($rows);
    }

    public function show(int $id)
    {
        $compra = DB::table('compra')->where('id', $id)->first();
        if ($compra) { $compra->total = $compra->Total; $compra->fecha = $compra->Fecha; }
        abort_if(!$compra, 404, 'Compra no encontrada.');
        $compra->items = DB::table('compra_producto as cp')->join('producto as p', 'p.id', '=', 'cp.id_producto')
            ->select('cp.*', 'p.Nombre')->where('cp.id_compra', $id)->get();
        return response()->json($compra);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'proveedor' => ['required', 'string', 'max:160'],
            'nro_documento' => ['nullable', 'string', 'max:80'],
            'Fecha' => ['nullable', 'date'],
            'observacion' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id_producto' => ['required', 'integer'],
            'items.*.cantidad' => ['required', 'integer', 'min:1'],
            'items.*.costo_unitario' => ['required', 'numeric', 'min:0'],
        ]);

        $id = DB::transaction(function () use ($data) {
            $total = collect($data['items'])->sum(fn ($item) => (int) $item['cantidad'] * (float) $item['costo_unitario']);
            $id = (int) DB::table('compra')->insertGetId([
                'proveedor' => $data['proveedor'],
                'nro_documento' => $data['nro_documento'] ?? null,
                'Total' => round($total, 2),
                'Fecha' => $data['Fecha'] ?? now()->toDateString(),
                'observacion' => $data['observacion'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
                ...(\Illuminate\Support\Facades\Schema::hasColumn('compra', 'estado_operacion') ? ['estado_operacion' => 'Activo'] : []),
            ]);

            foreach ($data['items'] as $item) {
                $producto = DB::table('producto')->where('id', $item['id_producto'])->lockForUpdate()->first();
                if (!$producto) {
                    throw ValidationException::withMessages(['items' => 'Uno de los productos seleccionados no existe.']);
                }
                $cantidad = (int) $item['cantidad'];
                $costo = (float) $item['costo_unitario'];
                $anterior = (int) $producto->Stock;
                $nuevo = $anterior + $cantidad;

                DB::table('compra_producto')->insert([
                    'id_compra' => $id,
                    'id_producto' => $producto->id,
                    'cantidad' => $cantidad,
                    'costo_unitario' => $costo,
                    'subtotal' => round($cantidad * $costo, 2),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('producto')->where('id', $producto->id)->update(['Stock' => $nuevo]);
                DB::table('movimiento_stock')->insert([
                    'id_producto' => $producto->id,
                    'tipo' => 'entrada',
                    'cantidad' => $cantidad,
                    'stock_anterior' => $anterior,
                    'stock_nuevo' => $nuevo,
                    'motivo' => "Compra #{$id} - {$data['proveedor']}",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            return $id;
        });

        return response()->json(['message' => 'Compra registrada y stock incrementado.', 'id' => $id], 201);
    }

    public function updateStatus(Request $request, int $id)
    {
        $data = $request->validate([
            'estado_operacion' => ['required', 'in:Activo,Finalizado'],
        ]);

        $compra = DB::table('compra')->where('id', $id)->first();
        abort_if(!$compra, 404, 'Compra no encontrada.');

        DB::table('compra')->where('id', $id)->update([
            'estado_operacion' => $data['estado_operacion'],
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Estado de compra actualizado.']);
    }

    public function pdf(int $id, SimplePdfService $pdf)
    {
        $compra = DB::table('compra')->where('id', $id)->first();
        abort_if(!$compra, 404, 'Compra no encontrada.');
        $items = DB::table('compra_producto as cp')->join('producto as p', 'p.id', '=', 'cp.id_producto')
            ->select('cp.*', 'p.Nombre')->where('cp.id_compra', $id)->get();

        $lines = [
            'Proveedor: ' . $compra->proveedor,
            'Documento: ' . ($compra->nro_documento ?: '-'),
            'Fecha: ' . $compra->Fecha,
            '', 'DETALLE',
        ];
        foreach ($items as $item) {
            $lines[] = sprintf('%s | %d x Bs %.2f = Bs %.2f', $item->Nombre, $item->cantidad, $item->costo_unitario, $item->subtotal);
        }
        $lines[] = '';
        $lines[] = sprintf('TOTAL COMPRA: Bs %.2f', $compra->Total);
        $binary = $pdf->make("COMPROBANTE DE COMPRA #{$id}", $lines);
        return response($binary, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => "inline; filename=compra-{$id}.pdf"]);
    }
}
