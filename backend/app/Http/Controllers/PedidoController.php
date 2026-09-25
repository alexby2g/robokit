<?php

namespace App\Http\Controllers;

use App\Services\SimplePdfService;
use App\Services\VentaService;
use App\Services\MediaStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PedidoController extends Controller
{
    public function __construct(private MediaStorage $media) {}
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

        $id = $ventas->crear((int) $data['id_usuario'], $data['items'], $data['Fecha'] ?? null, 'Pendiente', 'Mostrador', $data['metodo_pago'] ?? 'Efectivo');
        return response()->json(['message' => 'Venta registrada como pendiente. El stock se descontará al entregar.', 'id' => $id], 201);
    }

    public function update(Request $request, int $id, VentaService $ventas)
    {
        $data = $request->validate([
            'Estado' => ['required', 'string', 'max:50'],
            'evidencia_entrega' => ['nullable', 'file', 'max:12288', 'mimes:jpg,jpeg,png,webp'],
        ], [
            'evidencia_entrega.max' => 'La evidencia no debe superar 12 MB.',
            'evidencia_entrega.mimes' => 'La evidencia de entrega debe ser una fotografía JPG, PNG o WEBP.',
        ]);

        $pedido = DB::table('pedido')->where('id', $id)->first();
        abort_if(!$pedido, 404, 'Pedido no encontrado.');

        if (strcasecmp($data['Estado'], 'Entregado') === 0) {
            if (Schema::hasTable('pagos')) {
                $pago = DB::table('pagos')->where('pedido_id', $id)->first();
                if (!$pago || strcasecmp((string) $pago->estado, 'Verificado') !== 0) {
                    return response()->json(['message' => 'Debes verificar el pago antes de marcar el pedido como entregado.'], 422);
                }
            }

            $tieneEvidencia = Schema::hasColumn('pedido', 'evidencia_entrega') && !empty($pedido->evidencia_entrega);
            if (!$tieneEvidencia && !$request->hasFile('evidencia_entrega')) {
                return response()->json(['message' => 'Debes adjuntar una fotografía como evidencia antes de marcar el pedido como entregado.'], 422);
            }

            if ($request->hasFile('evidencia_entrega')) {
                abort_unless(Schema::hasColumn('pedido', 'evidencia_entrega'), 422, 'Ejecuta la migración de evidencia de entrega.');
                if (!empty($pedido->evidencia_entrega)) $this->media->deletePrivate($pedido->evidencia_entrega);
                $path = $this->media->storePrivate($request->file('evidencia_entrega'), 'entregas');
                $update = ['evidencia_entrega' => $path];
                if (Schema::hasColumn('pedido', 'evidencia_entrega_at')) $update['evidencia_entrega_at'] = now();
                DB::table('pedido')->where('id', $id)->update($update);
            }
        }

        $ventas->cambiarEstado($id, $data['Estado']);
        return response()->json(['message' => 'Estado actualizado.']);
    }

    public function deliveryProof(Request $request, int $id)
    {
        abort_unless(Schema::hasColumn('pedido', 'evidencia_entrega'), 404);
        $pedido = DB::table('pedido')->where('id', $id)->first();
        abort_if(!$pedido || empty($pedido->evidencia_entrega), 404, 'Este pedido no tiene evidencia de entrega.');

        try {
            $file = $this->media->privateFile($pedido->evidencia_entrega);
        } catch (\RuntimeException) {
            abort(404, 'Evidencia no encontrada.');
        }

        return response($file['contents'], 200, [
            'Content-Type' => $file['mime'],
            'Content-Disposition' => 'inline; filename="'.$file['filename'].'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
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
