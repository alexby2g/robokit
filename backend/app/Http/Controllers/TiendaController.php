<?php

namespace App\Http\Controllers;

use App\Services\VentaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TiendaController extends Controller
{
    public function catalog(Request $request)
    {
        $query = DB::table('producto as p')
            ->leftJoin('categoria as c', 'c.id', '=', 'p.id_categoria')
            ->select('p.*', 'c.Nombre as categoria_nombre')
            ->where('p.estado_publicacion', 'Publicado');

        if ($request->boolean('destacados')) $query->where('p.destacado', true);
        if ($request->filled('categoria')) $query->where('p.id_categoria', (int) $request->input('categoria'));
        if ($request->filled('q')) {
            $term = trim((string) $request->input('q'));
            $query->where(function ($q) use ($term) {
                $q->where('p.Nombre', 'like', "%{$term}%")
                    ->orWhere('p.Descripcion', 'like', "%{$term}%");
            });
        }

        $productos = $query
            ->orderByDesc('p.destacado')
            ->orderBy('p.orden_catalogo')
            ->orderBy('p.Nombre')
            ->get();

        $this->attachImagesAndStock($productos);
        $this->attachTraining($productos);
        return response()->json(['productos' => $productos]);
    }

    public function product(string $slugOrId)
    {
        $producto = DB::table('producto as p')
            ->leftJoin('categoria as c', 'c.id', '=', 'p.id_categoria')
            ->select('p.*', 'c.Nombre as categoria_nombre')
            ->where('p.estado_publicacion', 'Publicado')
            ->where(function ($q) use ($slugOrId) {
                $q->where('p.slug', $slugOrId);
                if (ctype_digit($slugOrId)) $q->orWhere('p.id', (int) $slugOrId);
            })
            ->first();

        abort_if(!$producto, 404, 'Producto no disponible.');
        $items = collect([$producto]);
        $this->attachImagesAndStock($items);
        $this->attachTraining($items);
        return response()->json(['producto' => $items->first()]);
    }

    public function categories()
    {
        $categorias = DB::table('categoria as c')
            ->join('producto as p', 'p.id_categoria', '=', 'c.id')
            ->where('p.estado_publicacion', 'Publicado')
            ->select('c.id', 'c.Nombre', 'c.Descripcion')
            ->distinct()
            ->orderBy('c.Nombre')
            ->get();

        return response()->json(['categorias' => $categorias]);
    }

    public function store(Request $request, VentaService $ventas)
    {
        $authUser = auth('sanctum')->user();
        $loggedClient = $authUser && $authUser->role === 'cliente' && $authUser->usuario_id;

        $rules = [
            'tipo_entrega' => ['required', Rule::in(['Recojo', 'Delivery'])],
            'direccion_entrega' => ['nullable', 'string', 'max:500'],
            'notas_cliente' => ['nullable', 'string', 'max:800'],
            'metodo_pago' => ['nullable', Rule::in(['QR', 'Transferencia', 'Efectivo'])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id_producto' => ['required', 'integer'],
            'items.*.cantidad' => ['required', 'integer', 'min:1'],
        ];

        if (!$loggedClient) {
            $rules += [
                'cliente.Nombre' => ['required', 'string', 'max:100'],
                'cliente.Apellido' => ['required', 'string', 'max:100'],
                'cliente.Telefono' => ['required', 'string', 'max:30'],
                'cliente.Direccion_envio' => ['nullable', 'string', 'max:500'],
            ];
        } else {
            $rules += [
                'cliente.Nombre' => ['nullable', 'string', 'max:100'],
                'cliente.Apellido' => ['nullable', 'string', 'max:100'],
                'cliente.Telefono' => ['nullable', 'string', 'max:30'],
                'cliente.Direccion_envio' => ['nullable', 'string', 'max:500'],
            ];
        }

        $data = $request->validate($rules, [
            'tipo_entrega.required' => 'Debes seleccionar una forma de entrega.',
            'tipo_entrega.in' => 'La forma de entrega seleccionada no es válida.',
            'direccion_entrega.max' => 'La dirección no debe superar 500 caracteres.',
            'notas_cliente.max' => 'La nota del pedido no debe superar 800 caracteres.',
                        'metodo_pago.in' => 'El método de pago seleccionado no es válido.',
            'items.required' => 'Tu carrito está vacío.',
            'items.array' => 'Los productos del pedido no son válidos.',
            'items.min' => 'Agrega al menos un producto al carrito.',
            'items.*.id_producto.required' => 'Uno de los productos del pedido no es válido.',
            'items.*.cantidad.required' => 'Debes indicar la cantidad del producto.',
            'items.*.cantidad.integer' => 'La cantidad debe ser un número entero.',
            'items.*.cantidad.min' => 'La cantidad debe ser al menos 1.',
            'cliente.Nombre.required' => 'El nombre es obligatorio.',
            'cliente.Apellido.required' => 'El apellido es obligatorio.',
            'cliente.Telefono.required' => 'El teléfono o WhatsApp es obligatorio.',
            'cliente.Nombre.max' => 'El nombre no debe superar 100 caracteres.',
            'cliente.Apellido.max' => 'El apellido no debe superar 100 caracteres.',
            'cliente.Telefono.max' => 'El teléfono no debe superar 30 caracteres.',
        ]);
        $config = DB::table('catalogo_config')->where('id', 1)->first();

        if ($data['tipo_entrega'] === 'Delivery' && $config && !$config->delivery_habilitado) {
            return response()->json(['message' => 'Delivery no está habilitado actualmente.'], 422);
        }
        if ($data['tipo_entrega'] === 'Recojo' && $config && !$config->recojo_habilitado) {
            return response()->json(['message' => 'Recojo no está habilitado actualmente.'], 422);
        }
        if ($data['tipo_entrega'] === 'Delivery' && empty(trim((string) ($data['direccion_entrega'] ?? '')))) {
            throw ValidationException::withMessages([
                'direccion_entrega' => ['La dirección es obligatoria para delivery.'],
            ]);
        }

        if ($loggedClient) {
            $usuarioId = (int) $authUser->usuario_id;
            $usuario = DB::table('usuario')->where('id', $usuarioId)->first();
            abort_if(!$usuario, 422, 'La cuenta no está vinculada a un cliente válido.');
            if (!empty($data['direccion_entrega'])) {
                DB::table('usuario')->where('id', $usuarioId)->update(['Direccion_envio' => $data['direccion_entrega']]);
            }
        } else {
            $cliente = $data['cliente'];
            $usuario = DB::table('usuario')->where('Telefono', $cliente['Telefono'])->first();

            if ($usuario) {
                $usuarioId = (int) $usuario->id;
                DB::table('usuario')->where('id', $usuarioId)->update([
                    'Nombre' => $cliente['Nombre'],
                    'Apellido' => $cliente['Apellido'],
                    'Direccion_envio' => $data['direccion_entrega'] ?? $cliente['Direccion_envio'] ?? $usuario->Direccion_envio,
                ]);
            } else {
                $usuarioId = (int) DB::table('usuario')->insertGetId([
                    'Nombre' => $cliente['Nombre'],
                    'Apellido' => $cliente['Apellido'],
                    'Telefono' => $cliente['Telefono'],
                    'Direccion_envio' => $data['direccion_entrega'] ?? $cliente['Direccion_envio'] ?? null,
                    'Email' => null,
                ]);
            }
        }

        $pedido = $ventas->crearOnline(
            $usuarioId,
            $data['items'],
            $data['tipo_entrega'],
            $data['direccion_entrega'] ?? null,
            $data['notas_cliente'] ?? null,
            ($data['metodo_pago'] ?? 'QR')
        );

        return response()->json([
            'message' => 'Solicitud de pedido registrada. El pedido se confirmará cuando envíes el comprobante y el pago sea verificado.',
            'pedido' => $pedido,
            'id' => $pedido['id'],
            'codigo_seguimiento' => $pedido['codigo'],
            'cuenta_cliente' => (bool) $loggedClient,
        ], 201);
    }

    public function tracking(string $codigo)
    {
        if (!Schema::hasColumn('pedido', 'codigo_seguimiento')) abort(404);

        $pedido = DB::table('pedido as p')
            ->leftJoin('usuario as u', 'u.id', '=', 'p.id_usuario')
            ->select('p.*', 'u.Nombre', 'u.Apellido')
            ->where('p.codigo_seguimiento', $codigo)
            ->where('p.canal', 'Online')
            ->first();
        abort_if(!$pedido, 404, 'No encontramos un pedido con ese código.');

        $pedido->items = DB::table('pedido_producto as pp')
            ->join('producto as pr', 'pr.id', '=', 'pp.id_producto')
            ->select('pp.id_producto', 'pp.cantidad', 'pp.precio_unitario', 'pp.subtotal', 'pr.Nombre')
            ->where('pp.id_pedido', $pedido->id)
            ->get();
        $pedido->pago = Schema::hasTable('pagos') ? DB::table('pagos')->where('pedido_id', $pedido->id)->first() : null;

        return response()->json(['pedido' => $pedido]);
    }

    private function attachTraining($productos): void
    {
        if (!Schema::hasTable('curso_producto') || !Schema::hasTable('cursos')) {
            foreach ($productos as $producto) $producto->capacitaciones = collect();
            return;
        }

        $ids = $productos->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (!$ids) return;

        $rows = DB::table('curso_producto as cp')
            ->join('cursos as cu', 'cu.id', '=', 'cp.curso_id')
            ->whereIn('cp.producto_id', $ids)
            ->where('cu.estado', 'activo')
            ->select('cp.producto_id', 'cu.id', 'cu.titulo', 'cu.descripcion', 'cu.imagen')
            ->orderByDesc('cu.id')
            ->get()
            ->groupBy('producto_id');

        foreach ($productos as $producto) {
            $producto->capacitaciones = ($rows[$producto->id] ?? collect())->values();
        }
    }

    private function attachImagesAndStock($productos): void
    {
        $ids = $productos->pluck('id');
        $imagenes = DB::table('imagenes')
            ->whereIn('id_producto', $ids)
            ->orderByDesc('es_principal')
            ->orderBy('orden')
            ->orderBy('id')
            ->get()
            ->groupBy('id_producto');

        foreach ($productos as $producto) {
            $reservado = (int) ($producto->stock_reservado ?? 0);
            $producto->Stock_reservado = $reservado;
            $producto->Disponible = max(0, (int) $producto->Stock - $reservado);
            $producto->imagenes = ($imagenes[$producto->id] ?? collect())->values();
        }
    }
}
