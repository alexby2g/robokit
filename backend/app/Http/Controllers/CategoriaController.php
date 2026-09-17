<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CategoriaController extends Controller
{
    // 1. LISTAR TODAS LAS CATEGORÍAS
    public function listar(): JsonResponse
    {
        $categorias = Categoria::query()->orderByDesc('id')->get();
        return response()->json([
            'mensaje' => 'Categorías listadas correctamente.',
            'OK' => true,
            'categorias' => $categorias
        ], 200);
    }

    // 2. REGISTRAR CATEGORÍA
    public function registrarCategoria(Request $request): JsonResponse
    {
        $request->validate([
            'Nombre' => 'required|string|max:100', // <-- Cambiado aquí
        ], [
            'Nombre.required' => 'El nombre de la categoría es obligatorio.' // <-- Cambiado aquí
        ]);

        $categoria = Categoria::create($request->all());

        return response()->json([
            'mensaje' => 'Registro Correcto.',
            'OK' => true,
            'categoria' => $categoria
        ], 201);
    }

    // 3. ACTUALIZAR CATEGORÍA
    public function actualizar(Request $request, $id): JsonResponse
    {
        $categoria = Categoria::find($id);

        if (!$categoria) {
            return response()->json([
                'mensaje' => 'Categoría no encontrada.',
                'OK' => false,
            ], 404);
        }

        $request->validate([
            'Nombre' => 'sometimes|string|max:100', // <-- Cambiado aquí
        ]);

        $categoria->update($request->all());

        return response()->json([
            'mensaje' => 'Categoría actualizada correctamente.',
            'OK' => true,
            'categoria' => $categoria
        ], 200);
    }

    // 4. ELIMINAR CATEGORÍA
    public function borrar($id): JsonResponse
    {
        $categoria = Categoria::find($id);

        if (!$categoria) {
            return response()->json([
                'mensaje' => 'Categoría no encontrada.',
                'OK' => false,
            ], 404);
        }

        $categoria->delete();

        return response()->json([
            'mensaje' => 'Categoría eliminada correctamente.',
            'OK' => true,
        ], 200);
    }

    // 5. VER UNA CATEGORÍA ESPECÍFICA (Para evitar conflictos en Insomnia)
    public function show($id): JsonResponse
    {
        $categoria = Categoria::find($id);

        if (!$categoria) {
            return response()->json([
                'mensaje' => 'Categoría no encontrada.',
                'OK' => false,
            ], 404);
        }

        return response()->json([
            'mensaje' => 'Categoría encontrada.',
            'OK' => true,
            'categoria' => $categoria
        ], 200);
    }
}