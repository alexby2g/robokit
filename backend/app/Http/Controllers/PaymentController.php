<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    public function index(Request $request)
    {
        $query = DB::table('pagos as pg')
            ->join('pedido as p', 'p.id', '=', 'pg.pedido_id')
            ->leftJoin('usuario as u', 'u.id', '=', 'p.id_usuario')
            ->select('pg.*', 'p.Estado as estado_pedido', 'p.codigo_seguimiento', 'p.Fecha', 'u.Nombre', 'u.Apellido', 'u.Telefono');

        if ($request->filled('estado')) $query->where('pg.estado', $request->string('estado')->toString());
        return response()->json(['pagos' => $query->orderByDesc('pg.id')->get()]);
    }

    public function clientIndex(Request $request)
    {
        $user = $request->user();
        abort_if(!$user?->usuario_id, 422, 'La cuenta no está vinculada a un cliente.');

        $pagos = DB::table('pagos as pg')
            ->join('pedido as p', 'p.id', '=', 'pg.pedido_id')
            ->where('p.id_usuario', $user->usuario_id)
            ->where('p.canal', 'Online')
            ->select('pg.*', 'p.codigo_seguimiento', 'p.Estado as estado_pedido', 'p.Fecha')
            ->orderByDesc('pg.id')
            ->get();
        return response()->json(['pagos' => $pagos]);
    }

    public function report(Request $request, int $pedidoId)
    {
        $user = $request->user();
        abort_if(!$user?->usuario_id, 422, 'La cuenta no está vinculada a un cliente.');

        $pedido = DB::table('pedido')->where('id', $pedidoId)->where('id_usuario', $user->usuario_id)->where('canal', 'Online')->first();
        abort_if(!$pedido, 404, 'Pedido no encontrado.');

        $data = $request->validate([
            'metodo' => ['required', Rule::in(['QR', 'Transferencia', 'Efectivo'])],
            'referencia' => ['nullable', 'string', 'max:120'],
            'nota' => ['nullable', 'string', 'max:600'],
            'comprobante' => ['nullable', 'file', 'max:8192', 'mimes:jpg,jpeg,png,webp,pdf'],
        ], [
            'metodo.required' => 'Selecciona el método de pago.',
            'metodo.in' => 'El método de pago seleccionado no es válido.',
            'referencia.max' => 'La referencia no debe superar 120 caracteres.',
            'nota.max' => 'La nota no debe superar 600 caracteres.',
            'comprobante.max' => 'El comprobante no debe superar 8 MB.',
            'comprobante.mimes' => 'El comprobante debe ser JPG, PNG, WEBP o PDF.',
        ]);

        $pago = DB::table('pagos')->where('pedido_id', $pedidoId)->first();
        $path = $pago->comprobante ?? null;
        if ($request->hasFile('comprobante')) {
            if ($path && str_starts_with($path, 'pagos/')) Storage::disk('public')->delete($path);
            $path = $request->file('comprobante')->store('pagos', 'public');
        }

        $values = [
            'usuario_id' => $user->usuario_id,
            'metodo' => $data['metodo'],
            'monto' => $pedido->Total,
            'estado' => $data['metodo'] === 'Efectivo' ? 'Pendiente' : 'Reportado',
            'referencia' => $data['referencia'] ?? null,
            'comprobante' => $path,
            'nota' => $data['nota'] ?? null,
            'reportado_at' => $data['metodo'] === 'Efectivo' ? null : now(),
            'updated_at' => now(),
        ];

        if ($pago) DB::table('pagos')->where('id', $pago->id)->update($values);
        else DB::table('pagos')->insert(['pedido_id' => $pedidoId, 'created_at' => now(), ...$values]);

        DB::table('pedido')->where('id', $pedidoId)->update(['metodo_pago' => $data['metodo']]);

        if ($data['metodo'] !== 'Efectivo') {
            $this->notifications->staff('pago_reportado', 'Pago reportado', "Pedido #{$pedidoId} reportó un pago de Bs {$pedido->Total}.", '/admin/pagos', ['pedido_id' => $pedidoId]);
        }

        return response()->json(['message' => $data['metodo'] === 'Efectivo' ? 'Pago en efectivo registrado como pendiente.' : 'Pago reportado. Espera la verificación del personal.']);
    }

    public function updateStatus(Request $request, int $id)
    {
        $data = $request->validate([
            'estado' => ['required', Rule::in(['Pendiente', 'Reportado', 'Verificado', 'Rechazado', 'Reembolsado'])],
            'nota' => ['nullable', 'string', 'max:600'],
        ], [
            'estado.required' => 'Selecciona el estado del pago.',
            'estado.in' => 'El estado del pago no es válido.',
        ]);

        $pago = DB::table('pagos')->where('id', $id)->first();
        abort_if(!$pago, 404, 'Pago no encontrado.');
        $pedido = DB::table('pedido')->where('id', $pago->pedido_id)->first();
        abort_if(!$pedido, 404, 'Pedido no encontrado.');

        DB::table('pagos')->where('id', $id)->update([
            'estado' => $data['estado'],
            'nota' => $data['nota'] ?? $pago->nota,
            'verificado_por' => in_array($data['estado'], ['Verificado', 'Rechazado'], true) ? $request->user()->id : $pago->verificado_por,
            'verificado_at' => $data['estado'] === 'Verificado' ? now() : ($data['estado'] === 'Rechazado' ? null : $pago->verificado_at),
            'updated_at' => now(),
        ]);

        if ($data['estado'] === 'Verificado' && $pedido->Estado === 'Nuevo') {
            DB::table('pedido')->where('id', $pedido->id)->update(['Estado' => 'Confirmado']);
        }

        $title = $data['estado'] === 'Verificado' ? 'Pago verificado' : ($data['estado'] === 'Rechazado' ? 'Pago rechazado' : 'Estado de pago actualizado');
        $this->notifications->clientByUsuario((int) $pedido->id_usuario, 'pago', $title, "Pedido #{$pedido->id}: {$data['estado']}.", '/mi-cuenta', ['pedido_id' => $pedido->id, 'pago_id' => $id]);

        return response()->json(['message' => 'Estado del pago actualizado.']);
    }
}
