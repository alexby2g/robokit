<?php

namespace App\Http\Controllers;

use App\Models\Imagen;
use App\Models\Producto;
use App\Services\MediaStorage;
use Illuminate\Http\Request;

class ImagenController extends Controller
{
    public function __construct(private MediaStorage $media) {}

    public function subirImagen(Request $request)
    {
        $request->validate([
            'imagen' => ['required', 'file', 'max:12288'],
        ]);

        $ruta = $this->media->storePublic($request->file('imagen'), 'productos');

        return response()->json([
            'mensaje' => 'Imagen subida correctamente.',
            'ruta' => $ruta,
        ]);
    }

    public function subirProducto(Request $request)
    {
        $request->validate([
            'imagen' => ['required', 'file', 'max:12288'],
        ]);

        $ruta = $this->media->storePublic($request->file('imagen'), 'productos');

        $producto = Producto::create($request->except('imagen'));

        Imagen::create([
            'ruta' => $ruta,
            'id_producto' => $producto->id,
        ]);

        return response()->json([
            'mensaje' => 'Registro correcto.',
            'OK' => true,
        ]);
    }

    public function actualizarProducto(Request $request, $id)
    {
        $producto = Producto::find($id);

        if (!$producto) {
            return response()->json([
                'mensaje' => 'Producto no encontrado.',
                'OK' => false,
            ], 404);
        }

        $producto->update($request->except('imagen'));

        if ($request->hasFile('imagen')) {
            $request->validate([
                'imagen' => ['file', 'max:12288'],
            ]);

            $imagenExistente = Imagen::where('id_producto', $producto->id)->first();
            $nuevaRuta = $this->media->storePublic($request->file('imagen'), 'productos');

            if ($imagenExistente) {
                $rutaAnterior = $imagenExistente->ruta;
                $imagenExistente->update(['ruta' => $nuevaRuta]);
                $this->media->deletePublic($rutaAnterior);
            } else {
                Imagen::create([
                    'ruta' => $nuevaRuta,
                    'id_producto' => $producto->id,
                ]);
            }
        }

        return response()->json([
            'mensaje' => 'Producto e imagen actualizados correctamente.',
            'OK' => true,
        ]);
    }
}
