<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VentaService
{
    public function __construct(private NotificationService $notifications) {}
    public function crear(
        int $usuarioId,
        array $items,
        ?string $fecha = null,
        string $estado = 'Entregado',
        string $canal = 'Mostrador',
        string $metodoPago = 'Efectivo'
    ): int {
        return DB::transaction(function () use ($usuarioId, $items, $fecha, $estado, $canal, $metodoPago) {
            $this->validarUsuario($usuarioId);
            $detalle = $this->prepararDetalle($items, true);

            $pedido = [
                'id_usuario' => $usuarioId,
                'Total' => round(collect($detalle)->sum('subtotal'), 2),
                'Estado' => $estado,
                'Fecha' => $fecha ?: now()->toDateString(),
            ];

            if (Schema::hasColumn('pedido', 'canal')) $pedido['canal'] = $canal;
            if (Schema::hasColumn('pedido', 'tipo_entrega')) $pedido['tipo_entrega'] = 'Mostrador';
            if (Schema::hasColumn('pedido', 'stock_aplicado')) $pedido['stock_aplicado'] = 1;
            if (Schema::hasColumn('pedido', 'reserva_aplicada')) $pedido['reserva_aplicada'] = 0;
            if (Schema::hasColumn('pedido', 'metodo_pago')) $pedido['metodo_pago'] = $metodoPago;

            $pedidoId = (int) DB::table('pedido')->insertGetId($pedido);

            foreach ($detalle as $linea) {
                $this->insertarDetalle($pedidoId, $linea);
                $this->descontarStockFisico($pedidoId, $linea, "Venta de mostrador #{$pedidoId}");
            }

            $this->crearPagoInicial($pedidoId, $usuarioId, (float) $pedido['Total'], $metodoPago, 'Verificado');
            $this->notifications->staff('venta', 'Venta registrada', "Venta de mostrador #{$pedidoId} por Bs {$pedido['Total']}.", '/admin/pedidos', ['pedido_id' => $pedidoId]);
            return $pedidoId;
        });
    }

    public function crearOnline(
        int $usuarioId,
        array $items,
        string $tipoEntrega,
        ?string $direccion = null,
        ?string $nota = null,
        string $metodoPago = 'Efectivo'
    ): array {
        return DB::transaction(function () use ($usuarioId, $items, $tipoEntrega, $direccion, $nota, $metodoPago) {
            $this->validarUsuario($usuarioId);
            $detalle = $this->prepararDetalle($items, false);
            $codigo = $this->generarCodigoSeguimiento();

            $pedido = [
                'id_usuario' => $usuarioId,
                'Total' => round(collect($detalle)->sum('subtotal'), 2),
                'Estado' => 'Nuevo',
                'Fecha' => now()->toDateString(),
            ];

            if (Schema::hasColumn('pedido', 'canal')) $pedido['canal'] = 'Online';
            if (Schema::hasColumn('pedido', 'tipo_entrega')) $pedido['tipo_entrega'] = $tipoEntrega;
            if (Schema::hasColumn('pedido', 'direccion_entrega')) $pedido['direccion_entrega'] = $direccion;
            if (Schema::hasColumn('pedido', 'notas_cliente')) $pedido['notas_cliente'] = $nota;
            if (Schema::hasColumn('pedido', 'codigo_seguimiento')) $pedido['codigo_seguimiento'] = $codigo;
            if (Schema::hasColumn('pedido', 'stock_aplicado')) $pedido['stock_aplicado'] = 0;
            if (Schema::hasColumn('pedido', 'reserva_aplicada')) $pedido['reserva_aplicada'] = 1;
            if (Schema::hasColumn('pedido', 'metodo_pago')) $pedido['metodo_pago'] = $metodoPago;

            $pedidoId = (int) DB::table('pedido')->insertGetId($pedido);

            foreach ($detalle as $linea) {
                $this->insertarDetalle($pedidoId, $linea);
                $this->reservarStock($linea);
            }

            $this->crearPagoInicial($pedidoId, $usuarioId, (float) $pedido['Total'], $metodoPago, 'Pendiente');
            $this->notifications->staff('pedido_nuevo', 'Nueva solicitud online', "Pedido #{$pedidoId} por Bs {$pedido['Total']}.", '/admin/pedidos-online', ['pedido_id' => $pedidoId]);
            return ['id' => $pedidoId, 'codigo' => $codigo, 'estado' => 'Nuevo', 'metodo_pago' => $metodoPago];
        });
    }

    public function cambiarEstado(int $pedidoId, string $nuevoEstado): void
    {
        $permitidos = ['Nuevo', 'Confirmado', 'Preparando', 'Listo para entrega', 'En camino', 'Entregado', 'Cancelado', 'Pendiente'];
        if (!in_array($nuevoEstado, $permitidos, true)) {
            throw ValidationException::withMessages(['Estado' => 'Estado no válido.']);
        }

        DB::transaction(function () use ($pedidoId, $nuevoEstado) {
            $pedido = DB::table('pedido')->where('id', $pedidoId)->lockForUpdate()->first();
            if (!$pedido) abort(404, 'Venta no encontrada.');

            $actual = (string) $pedido->Estado;
            if ($actual === $nuevoEstado) return;

            $canal = Schema::hasColumn('pedido', 'canal') ? (string) ($pedido->canal ?? 'Mostrador') : 'Mostrador';

            if (strcasecmp($canal, 'Online') === 0) {
                $this->cambiarEstadoOnline($pedido, $nuevoEstado);
            } else {
                $this->cambiarEstadoMostrador($pedido, $nuevoEstado);
            }
        });
    }

    private function cambiarEstadoOnline(object $pedido, string $nuevoEstado): void
    {
        $pedidoId = (int) $pedido->id;
        $actual = (string) $pedido->Estado;

        if (strcasecmp($actual, 'Entregado') === 0 && strcasecmp($nuevoEstado, 'Entregado') !== 0) {
            throw ValidationException::withMessages(['Estado' => 'Un pedido entregado ya cerró inventario y no puede volver a un estado anterior.']);
        }

        $reservaAplicada = Schema::hasColumn('pedido', 'reserva_aplicada') ? (bool) $pedido->reserva_aplicada : false;
        $stockAplicado = Schema::hasColumn('pedido', 'stock_aplicado') ? (bool) $pedido->stock_aplicado : false;

        if (strcasecmp($nuevoEstado, 'Cancelado') === 0) {
            if ($reservaAplicada) {
                $this->liberarReserva($pedidoId);
                $this->actualizarFlags($pedidoId, false, false);
            }
            DB::table('pedido')->where('id', $pedidoId)->update(['Estado' => 'Cancelado']);
            $this->notifications->clientByUsuario((int) $pedido->id_usuario, 'pedido', 'Pedido cancelado', "Tu pedido #{$pedidoId} fue cancelado.", '/mi-cuenta', ['pedido_id' => $pedidoId]);
            return;
        }

        if (strcasecmp($actual, 'Cancelado') === 0 && strcasecmp($nuevoEstado, 'Cancelado') !== 0) {
            $this->reaplicarReserva($pedidoId);
            $reservaAplicada = true;
            $stockAplicado = false;
            $this->actualizarFlags($pedidoId, $stockAplicado, $reservaAplicada);
        }

        if (strcasecmp($nuevoEstado, 'Entregado') === 0 && !$stockAplicado) {
            if (Schema::hasTable('pagos')) {
                $pago = DB::table('pagos')->where('pedido_id', $pedidoId)->first();
                if ($pago && $pago->metodo !== 'Efectivo' && $pago->estado !== 'Verificado') {
                    throw ValidationException::withMessages(['Estado' => 'Debes verificar el pago antes de marcar el pedido como entregado.']);
                }
                if ($pago && $pago->metodo === 'Efectivo' && $pago->estado !== 'Verificado') {
                    DB::table('pagos')->where('id', $pago->id)->update([
                        'estado' => 'Verificado',
                        'verificado_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $this->notifications->clientByUsuario((int) $pedido->id_usuario, 'pago', 'Pago en efectivo confirmado', "El pago del pedido #{$pedidoId} fue confirmado al entregar.", '/mi-cuenta', ['pedido_id' => $pedidoId]);
                }
            }
            if (!$reservaAplicada) {
                $this->reaplicarReserva($pedidoId);
            }
            $this->finalizarReservaComoSalida($pedidoId);
            $this->actualizarFlags($pedidoId, true, false);

            $updates = ['Estado' => 'Entregado'];
            if (Schema::hasColumn('pedido', 'fecha_entregado')) $updates['fecha_entregado'] = now();
            DB::table('pedido')->where('id', $pedidoId)->update($updates);
            $this->notifications->clientByUsuario((int) $pedido->id_usuario, 'pedido', 'Pedido entregado', "Tu pedido #{$pedidoId} fue entregado.", '/mi-cuenta', ['pedido_id' => $pedidoId]);
            $this->notifications->staff('venta_completada', 'Venta online completada', "Pedido #{$pedidoId} entregado por Bs {$pedido->Total}.", '/admin/pedidos-online', ['pedido_id' => $pedidoId]);
            return;
        }

        DB::table('pedido')->where('id', $pedidoId)->update(['Estado' => $nuevoEstado]);
        $this->notifications->clientByUsuario((int) $pedido->id_usuario, 'pedido', 'Estado de pedido actualizado', "Tu pedido #{$pedidoId} ahora está: {$nuevoEstado}.", '/mi-cuenta', ['pedido_id' => $pedidoId, 'estado' => $nuevoEstado]);
    }

    private function cambiarEstadoMostrador(object $pedido, string $nuevoEstado): void
    {
        $pedidoId = (int) $pedido->id;
        $antesCancelado = mb_strtolower((string) $pedido->Estado) === 'cancelado';
        $ahoraCancelado = mb_strtolower($nuevoEstado) === 'cancelado';
        $stockAplicado = Schema::hasColumn('pedido', 'stock_aplicado') ? (bool) $pedido->stock_aplicado : !$antesCancelado;

        if (!$antesCancelado && $ahoraCancelado && $stockAplicado) {
            $this->restaurarStock($pedidoId);
            $this->actualizarFlags($pedidoId, false, false);
        } elseif ($antesCancelado && !$ahoraCancelado && !$stockAplicado) {
            $this->reaplicarStock($pedidoId);
            $this->actualizarFlags($pedidoId, true, false);
        }

        DB::table('pedido')->where('id', $pedidoId)->update(['Estado' => $nuevoEstado]);
    }

    private function prepararDetalle(array $items, bool $aceptarPrecioCliente): array
    {
        $detalle = [];
        foreach ($items as $item) {
            $cantidad = (int) ($item['cantidad'] ?? 0);
            if ($cantidad <= 0) throw ValidationException::withMessages(['items' => 'Todas las cantidades deben ser mayores a cero.']);

            $producto = DB::table('producto')->where('id', (int) ($item['id_producto'] ?? 0))->lockForUpdate()->first();
            if (!$producto) throw ValidationException::withMessages(['items' => 'Uno de los productos ya no existe.']);

            $reservado = Schema::hasColumn('producto', 'stock_reservado') ? (int) ($producto->stock_reservado ?? 0) : 0;
            $disponible = max(0, (int) $producto->Stock - $reservado);
            if ($disponible < $cantidad) {
                throw ValidationException::withMessages(['items' => "Stock disponible insuficiente para {$producto->Nombre}. Disponible: {$disponible}."]);
            }

            $precio = $aceptarPrecioCliente && isset($item['precio_unitario']) && $item['precio_unitario'] !== ''
                ? (float) $item['precio_unitario']
                : (float) $producto->Precio;
            $subtotal = round($precio * $cantidad, 2);
            $detalle[] = compact('producto', 'cantidad', 'precio', 'subtotal');
        }

        if (!$detalle) throw ValidationException::withMessages(['items' => 'Agrega al menos un producto.']);
        return $detalle;
    }

    private function insertarDetalle(int $pedidoId, array $linea): void
    {
        $pivot = [
            'id_pedido' => $pedidoId,
            'id_producto' => $linea['producto']->id,
            'cantidad' => $linea['cantidad'],
        ];
        if (Schema::hasColumn('pedido_producto', 'precio_unitario')) $pivot['precio_unitario'] = $linea['precio'];
        if (Schema::hasColumn('pedido_producto', 'subtotal')) $pivot['subtotal'] = $linea['subtotal'];
        DB::table('pedido_producto')->insert($pivot);
    }

    private function reservarStock(array $linea): void
    {
        if (!Schema::hasColumn('producto', 'stock_reservado')) {
            throw ValidationException::withMessages(['items' => 'Falta ejecutar la migración v5 de pedidos online.']);
        }

        $producto = DB::table('producto')->where('id', $linea['producto']->id)->lockForUpdate()->first();
        $reservado = (int) ($producto->stock_reservado ?? 0);
        DB::table('producto')->where('id', $producto->id)->update(['stock_reservado' => $reservado + $linea['cantidad']]);
    }

    private function liberarReserva(int $pedidoId): void
    {
        if (!Schema::hasColumn('producto', 'stock_reservado')) return;
        foreach ($this->itemsPedido($pedidoId) as $item) {
            $producto = DB::table('producto')->where('id', $item->id_producto)->lockForUpdate()->first();
            if (!$producto) continue;
            $nuevo = max(0, (int) ($producto->stock_reservado ?? 0) - (int) $item->cantidad);
            DB::table('producto')->where('id', $producto->id)->update(['stock_reservado' => $nuevo]);
        }
    }

    private function reaplicarReserva(int $pedidoId): void
    {
        if (!Schema::hasColumn('producto', 'stock_reservado')) {
            throw ValidationException::withMessages(['Estado' => 'Ejecuta la migración v5 antes de reactivar pedidos online.']);
        }

        $items = $this->itemsPedido($pedidoId);
        foreach ($items as $item) {
            $producto = DB::table('producto')->where('id', $item->id_producto)->lockForUpdate()->first();
            if (!$producto) throw ValidationException::withMessages(['Estado' => 'Uno de los productos del pedido ya no existe.']);
            $reservado = (int) ($producto->stock_reservado ?? 0);
            $disponible = max(0, (int) $producto->Stock - $reservado);
            if ($disponible < (int) $item->cantidad) {
                throw ValidationException::withMessages(['Estado' => "No hay stock suficiente para reactivar el pedido. Producto: {$producto->Nombre}."]);
            }
        }

        foreach ($items as $item) {
            $producto = DB::table('producto')->where('id', $item->id_producto)->lockForUpdate()->first();
            DB::table('producto')->where('id', $producto->id)->update([
                'stock_reservado' => (int) ($producto->stock_reservado ?? 0) + (int) $item->cantidad,
            ]);
        }
    }

    private function finalizarReservaComoSalida(int $pedidoId): void
    {
        foreach ($this->itemsPedido($pedidoId) as $item) {
            $producto = DB::table('producto')->where('id', $item->id_producto)->lockForUpdate()->first();
            if (!$producto) continue;

            $cantidad = (int) $item->cantidad;
            $anterior = (int) $producto->Stock;
            if ($anterior < $cantidad) {
                throw ValidationException::withMessages(['Estado' => "Stock físico insuficiente para entregar {$producto->Nombre}."]);
            }

            $nuevo = $anterior - $cantidad;
            $update = ['Stock' => $nuevo];
            if (Schema::hasColumn('producto', 'stock_reservado')) {
                $update['stock_reservado'] = max(0, (int) ($producto->stock_reservado ?? 0) - $cantidad);
            }
            DB::table('producto')->where('id', $producto->id)->update($update);
            $this->registrarMovimiento($producto->id, 'salida', $cantidad, $anterior, $nuevo, "Pedido online #{$pedidoId} entregado");
        }
    }

    private function descontarStockFisico(int $pedidoId, array $linea, string $motivo): void
    {
        $producto = DB::table('producto')->where('id', $linea['producto']->id)->lockForUpdate()->first();
        $anterior = (int) $producto->Stock;
        $nuevo = $anterior - $linea['cantidad'];
        if ($nuevo < 0) throw ValidationException::withMessages(['items' => "Stock insuficiente para {$producto->Nombre}."]);
        DB::table('producto')->where('id', $producto->id)->update(['Stock' => $nuevo]);
        $this->registrarMovimiento($producto->id, 'salida', $linea['cantidad'], $anterior, $nuevo, $motivo);
    }

    private function restaurarStock(int $pedidoId): void
    {
        foreach ($this->itemsPedido($pedidoId) as $item) {
            $producto = DB::table('producto')->where('id', $item->id_producto)->lockForUpdate()->first();
            if (!$producto) continue;
            $anterior = (int) $producto->Stock;
            $nuevo = $anterior + (int) $item->cantidad;
            DB::table('producto')->where('id', $producto->id)->update(['Stock' => $nuevo]);
            $this->registrarMovimiento($producto->id, 'entrada', (int) $item->cantidad, $anterior, $nuevo, "Reversión venta #{$pedidoId} cancelada");
        }
    }

    private function reaplicarStock(int $pedidoId): void
    {
        $items = $this->itemsPedido($pedidoId);
        foreach ($items as $item) {
            $producto = DB::table('producto')->where('id', $item->id_producto)->lockForUpdate()->first();
            if (!$producto) {
                throw ValidationException::withMessages(['Estado' => 'Uno de los productos de la venta ya no existe.']);
            }
            $reservado = Schema::hasColumn('producto', 'stock_reservado') ? (int) ($producto->stock_reservado ?? 0) : 0;
            $disponible = max(0, (int) $producto->Stock - $reservado);
            if ($disponible < (int) $item->cantidad) {
                $nombre = $producto->Nombre ?? "#{$item->id_producto}";
                throw ValidationException::withMessages(['Estado' => "No hay stock suficiente para reactivar la venta. Producto: {$nombre}."]);
            }
        }

        foreach ($items as $item) {
            $producto = DB::table('producto')->where('id', $item->id_producto)->lockForUpdate()->first();
            $anterior = (int) $producto->Stock;
            $nuevo = $anterior - (int) $item->cantidad;
            DB::table('producto')->where('id', $producto->id)->update(['Stock' => $nuevo]);
            $this->registrarMovimiento($producto->id, 'salida', (int) $item->cantidad, $anterior, $nuevo, "Reactivación venta #{$pedidoId}");
        }
    }

    private function itemsPedido(int $pedidoId)
    {
        return DB::table('pedido_producto')->where('id_pedido', $pedidoId)->get();
    }

    private function registrarMovimiento(int $productoId, string $tipo, int $cantidad, int $anterior, int $nuevo, string $motivo): void
    {
        DB::table('movimiento_stock')->insert([
            'id_producto' => $productoId,
            'tipo' => $tipo,
            'cantidad' => $cantidad,
            'stock_anterior' => $anterior,
            'stock_nuevo' => $nuevo,
            'motivo' => $motivo,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function actualizarFlags(int $pedidoId, bool $stockAplicado, bool $reservaAplicada): void
    {
        $update = [];
        if (Schema::hasColumn('pedido', 'stock_aplicado')) $update['stock_aplicado'] = $stockAplicado ? 1 : 0;
        if (Schema::hasColumn('pedido', 'reserva_aplicada')) $update['reserva_aplicada'] = $reservaAplicada ? 1 : 0;
        if ($update) DB::table('pedido')->where('id', $pedidoId)->update($update);
    }

    private function crearPagoInicial(int $pedidoId, int $usuarioId, float $monto, string $metodo, string $estado): void
    {
        if (!Schema::hasTable('pagos')) return;
        DB::table('pagos')->updateOrInsert(
            ['pedido_id' => $pedidoId],
            [
                'usuario_id' => $usuarioId,
                'metodo' => $metodo,
                'monto' => $monto,
                'estado' => $estado,
                'verificado_at' => $estado === 'Verificado' ? now() : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    private function validarUsuario(int $usuarioId): void
    {
        if (!DB::table('usuario')->where('id', $usuarioId)->exists()) {
            throw ValidationException::withMessages(['id_usuario' => 'El cliente seleccionado no existe.']);
        }
    }

    private function generarCodigoSeguimiento(): string
    {
        do {
            $codigo = 'RBK-' . now()->format('ymd') . '-' . Str::upper(Str::random(6));
        } while (Schema::hasColumn('pedido', 'codigo_seguimiento') && DB::table('pedido')->where('codigo_seguimiento', $codigo)->exists());
        return $codigo;
    }
}
