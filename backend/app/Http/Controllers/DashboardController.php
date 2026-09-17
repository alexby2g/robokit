<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function totales()
    {
        $pgsql = DB::connection()->getDriverName() === 'pgsql';
        $estadoEntregadoSql = $pgsql ? "LOWER(\"Estado\") = 'entregado'" : "LOWER(Estado) = 'entregado'";
        $inventarioSql = $pgsql ? 'SUM("Precio" * "Stock") as total' : 'SUM(Precio * Stock) as total';
        $stockBajoSql = $pgsql ? '("Stock" - stock_reservado) <= 5' : '(Stock - stock_reservado) <= 5';
        $clienteSql = $pgsql ? "TRIM(CONCAT(COALESCE(\"u\".\"Nombre\",''),' ',COALESCE(\"u\".\"Apellido\",''))) as cliente" : "TRIM(CONCAT(COALESCE(u.Nombre,''),' ',COALESCE(u.Apellido,''))) as cliente";

        $inicio = now()->startOfMonth()->toDateString();
        $fin = now()->endOfMonth()->toDateString();
        $productos = DB::table('producto')->count();
        $clientes = DB::table('usuario')->count();

        $ventasBase = DB::table('pedido')->whereBetween('Fecha', [$inicio, $fin])->whereRaw($estadoEntregadoSql);
        $ventasMes = (float) (clone $ventasBase)->sum('Total');
        $pedidosMes = (clone $ventasBase)->count();
        $comprasMes = Schema::hasTable('compra') ? (float) DB::table('compra')->whereBetween('Fecha', [$inicio, $fin])->sum('Total') : 0;
        $comprasCantidadMes = Schema::hasTable('compra') ? DB::table('compra')->whereBetween('Fecha', [$inicio, $fin])->count() : 0;
        $valorInventario = (float) DB::table('producto')->selectRaw($inventarioSql)->value('total');
        $stockQuery = DB::table('producto');
        if (Schema::hasColumn('producto', 'stock_reservado')) {
            $stockQuery->whereRaw($stockBajoSql);
        } else {
            $stockQuery->where('Stock', '<=', 5);
        }
        $stockBajoProductos = (clone $stockQuery)->orderBy('Stock')->limit(10)->get();
        foreach ($stockBajoProductos as $producto) {
            $reservado = Schema::hasColumn('producto', 'stock_reservado') ? (int) ($producto->stock_reservado ?? 0) : 0;
            $producto->Disponible = max(0, (int) $producto->Stock - $reservado);
        }
        $stockBajoCount = (clone $stockQuery)->count();

        $online = ['nuevos' => 0, 'preparando' => 0, 'listos' => 0, 'en_camino' => 0];
        if (Schema::hasColumn('pedido', 'canal')) {
            $online['nuevos'] = DB::table('pedido')->where('canal', 'Online')->where('Estado', 'Nuevo')->count();
            $online['preparando'] = DB::table('pedido')->where('canal', 'Online')->whereIn('Estado', ['Confirmado', 'Preparando'])->count();
            $online['listos'] = DB::table('pedido')->where('canal', 'Online')->where('Estado', 'Listo para entrega')->count();
            $online['en_camino'] = DB::table('pedido')->where('canal', 'Online')->where('Estado', 'En camino')->count();
        }

        $meses = [];
        for ($i = 5; $i >= 0; $i--) {
            $fecha = now()->copy()->subMonths($i);
            $desde = $fecha->copy()->startOfMonth()->toDateString();
            $hasta = $fecha->copy()->endOfMonth()->toDateString();
            $meses[] = [
                'mes' => $fecha->format('M Y'),
                'ventas' => (float) DB::table('pedido')->whereBetween('Fecha', [$desde, $hasta])->whereRaw($estadoEntregadoSql)->sum('Total'),
                'compras' => Schema::hasTable('compra') ? (float) DB::table('compra')->whereBetween('Fecha', [$desde, $hasta])->sum('Total') : 0,
            ];
        }

        $ultimasVentas = DB::table('pedido as p')->leftJoin('usuario as u', 'u.id', '=', 'p.id_usuario')
            ->select('p.*', DB::raw($clienteSql))
            ->orderByDesc('p.id')->limit(5)->get();
        $ultimasCompras = Schema::hasTable('compra') ? DB::table('compra')->orderByDesc('id')->limit(5)->get()->each(function ($row) { $row->total = $row->Total; $row->fecha = $row->Fecha; }) : collect();

        return response()->json([
            'productos' => $productos,
            'clientes' => $clientes,
            'ventas_mes' => $ventasMes,
            'compras_mes' => $comprasMes,
            'pedidos_mes' => $pedidosMes,
            'compras_cantidad_mes' => $comprasCantidadMes,
            'valor_inventario' => $valorInventario,
            'stock_bajo' => $stockBajoProductos,
            'stock_bajo_count' => $stockBajoCount,
            'pedidos_online' => $online,
            'meses' => $meses,
            'ventas_recientes' => $ultimasVentas,
            'compras_recientes' => $ultimasCompras,
        ]);
    }
}
