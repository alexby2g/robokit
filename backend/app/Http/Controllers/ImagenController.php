<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Imagen;
use App\Models\Producto;
use Illuminate\Support\Facades\Storage; // Importante para gestionar el borrado de archivos viejos

class ImagenController extends Controller
{
    public function subirImagen(Request $request) {
        $ruta = $request->file('imagen')->store('productos', 'public');

        return response()->json([
            'mensaje' => 'Imagen subida correctamente.',
            'ruta' => $ruta,
        ]);
    }

    public function subirProducto(Request $request) {
        $ruta = $request->file('imagen')->store('productos', 'public');

        if ($ruta === false) {
            return response()->json([
                'mensaje' => 'Error en subir la imagen.',
                'OK' => false,
            ]);
        } else {
            $producto = Producto::create($request->all());
            $id_producto = $producto->id;

            $datosParaImagen = [
                'ruta' => $ruta,
                'id_producto' => $id_producto,
            ];

            $textImage = Imagen::create($datosParaImagen);

            return response()->json([
                'mensaje' => 'Registro Correcto.',
                'OK' => true,
            ]);
        }
    }

    // NUEVO MÉTODO: Actualizar Producto e Imagen asociada
    public function actualizarProducto(Request $request, $id) {
        $producto = Producto::find($id);

        if (!$producto) {
            return response()->json([
                'mensaje' => 'Producto no encontrado.',
                'OK' => false,
            ], 404);
        }

        // 1. Actualizar los datos del producto base (Nombre, Precio, Stock, etc.)
        $producto->update($request->all());

        // 2. Evaluar si se envió una nueva imagen en el formulario binario
        if ($request->hasFile('imagen')) {
            // Buscamos si el producto ya tiene un registro de imagen en la tabla 'imagenes'
            $imagenExistente = Imagen::where('id_producto', $producto->id)->first();

            if ($imagenExistente) {
                // Si el archivo físico existe en el almacenamiento, lo eliminamos para no acumular basura
                if (Storage::disk('public')->exists($imagenExistente->ruta)) {
                    Storage::disk('public')->delete($imagenExistente->ruta);
                }
                
                // Almacenamos el nuevo archivo binario
                $nuevaRuta = $request->file('imagen')->store('productos', 'public');
                
                // Actualizamos el registro de la base de datos con la nueva ruta
                $imagenExistente->update(['ruta' => $nuevaRuta]);
            } else {
                // En caso de que el producto no tuviera imagen previa, guardamos la nueva y creamos el registro
                $nuevaRuta = $request->file('imagen')->store('productos', 'public');
                Imagen::create([
                    'ruta' => $nuevaRuta,
                    'id_producto' => $producto->id
                ]);
            }
        }

        return response()->json([
            'mensaje' => 'Producto e imagen actualizados correctamente.',
            'OK' => true,
        ]);
    }
}