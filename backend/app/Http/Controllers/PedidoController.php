<?php

namespace App\Http\Controllers;

use App\Services\SimplePdfService;
use App\Services\VentaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PedidoController extends Controller
{
    public function index(Request $request)
    {
        $clienteSql = DB::connection()->getDriverName() === 'pgsql'
            ? "TRIM(CONCAT(COALESCE(\"u\".\"Nombre\",''),' ',COALESCE(\"u\".\"Apellido\",''))) as cliente"
            : "TRIM(CONCAT(COALESCE(u.Nombre,''),' ',COALESCE(u.Apellido,''))) as cliente";

        $query = DB::table('pedido as p')
            ->leftJoin('usuario as u', 'u.id', '=', 'p.id_usuario')
            ->select('p.*', 'u.Nombre', 'u.Apellido', 'u.Telefono', 'u.Direccion_envio', DB::raw($clienteSql));

        if (Schema::hasTable('pagos')) {
            $query->leftJoin('pagos as pg', 'pg.pedido_id', '=', 'p.id')
                ->addSelect('pg.id as pago_id', 'pg.estado as EstadoPago', 'pg.metodo as MetodoPago', 'pg.comprobante as ComprobantePago');
        }

        if ($request->filled('canal') && Schema::hasColumn('pedido', 'canal')) {
            $query->where('p.canal', $request->string('canal')->toString());
        }
        if ($request->filled('estado')) $query->where('p.Estado', $request->string('estado')->toString());

        return response()->json($query->orderByDesc('p.id')->get());
    }

    public function show(int $id)
    {
        $query = DB::table('pedido as p')
            ->leftJoin('usuario as u', 'u.id', '=', 'p.id_usuario')
            ->select('p.*', 'u.Nombre', 'u.Apellido', 'u.Telefono', 'u.Direccion_envio');
        if (Schema::hasTable('pagos')) {
            $query->leftJoin('pagos as pg', 'pg.pedido_id', '=', 'p.id')
                ->addSelect('pg.id as pago_id', 'pg.estado as EstadoPago', 'pg.metodo as MetodoPago', 'pg.referencia as ReferenciaPago', 'pg.comprobante as ComprobantePago', 'pg.nota as NotaPago');
        }
        $venta = $query->where('p.id', $id)->first();
        abort_if(!$venta, 404, 'Venta no encontrada.');

        $venta->items = DB::table('pedido_producto as pp')
            ->join('producto as pr', 'pr.id', '=', 'pp.id_producto')
            ->select('pp.*', 'pr.Nombre', 'pr.Precio')
            ->where('pp.id_pedido', $id)->get();
        return response()->json($venta);
    }

    public function store(Request $request, VentaService $ventas)
    {
        $data = $request->validate([
            'id_usuario' => ['required', 'integer'],
            'Fecha' => ['nullable', 'date'],
            'Estado' => ['nullable', 'string', 'max:50'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id_producto' => ['required', 'integer'],
            'items.*.cantidad' => ['required', 'integer', 'min:1'],
            'items.*.precio_unitario' => ['nullable', 'numeric', 'min:0'],
            'metodo_pago' => ['nullable', 'string', 'in:Efectivo,QR,Transferencia'],
        ]);

        $id = $ventas->crear((int) $data['id_usuario'], $data['items'], $data['Fecha'] ?? null, $data['Estado'] ?? 'Entregado', 'Mostrador', $data['metodo_pago'] ?? 'Efectivo');
        return response()->json(['message' => 'Venta registrada y stock actualizado.', 'id' => $id], 201);
    }

    public function update(Request $request, int $id, VentaService $ventas)
    {
        $data = $request->validate(['Estado' => ['required', 'string', 'max:50']]);
        $ventas->cambiarEstado($id, $data['Estado']);
        return response()->json(['message' => 'Estado actualizado.']);
    }

    public function pdf(int $id, SimplePdfService $pdf)
    {
        $venta = DB::table('pedido as p')->leftJoin('usuario as u', 'u.id', '=', 'p.id_usuario')
            ->select('p.*', 'u.Nombre', 'u.Apellido', 'u.Telefono', 'u.Direccion_envio')->where('p.id', $id)->first();
        abort_if(!$venta, 404, 'Venta no encontrada.');

        $items = DB::table('pedido_producto as pp')->join('producto as pr', 'pr.id', '=', 'pp.id_producto')
            ->select('pp.*', 'pr.Nombre', 'pr.Precio')->where('pp.id_pedido', $id)->get();

        $lines = [
            'Cliente: ' . trim(($venta->Nombre ?? '') . ' ' . ($venta->Apellido ?? '')),
            'Telefono: ' . ($venta->Telefono ?: '-'),
            'Fecha: ' . ($venta->Fecha ?: '-'),
            'Estado: ' . $venta->Estado,
        ];
        if (isset($venta->canal)) $lines[] = 'Canal: ' . $venta->canal;
        if (isset($venta->tipo_entrega)) $lines[] = 'Entrega: ' . $venta->tipo_entrega;
        if (isset($venta->codigo_seguimiento) && $venta->codigo_seguimiento) $lines[] = 'Seguimiento: ' . $venta->codigo_seguimiento;
        $lines[] = '';
        $lines[] = 'DETALLE';

        foreach ($items as $item) {
            $precio = Schema::hasColumn('pedido_producto', 'precio_unitario') && $item->precio_unitario !== null ? $item->precio_unitario : $item->Precio;
            $subtotal = Schema::hasColumn('pedido_producto', 'subtotal') && $item->subtotal !== null ? $item->subtotal : ((float) $precio * (int) $item->cantidad);
            $lines[] = sprintf('%s | %d x Bs %.2f = Bs %.2f', $item->Nombre, $item->cantidad, $precio, $subtotal);
        }
        $lines[] = '';
        $lines[] = sprintf('TOTAL: Bs %.2f', $venta->Total);

        $binary = $pdf->make("COMPROBANTE DE VENTA #{$id}", $lines);
        return response($binary, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => "inline; filename=venta-{$id}.pdf"]);
    }
}
