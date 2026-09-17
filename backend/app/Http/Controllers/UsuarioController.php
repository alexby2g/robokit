<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UsuarioController extends Controller
{
    // 1. LISTAR TODOS LOS USUARIOS
    public function listar(): JsonResponse
    {
        $usuarios = Usuario::query()->orderByDesc('id')->get();
        
        return response()->json([
            'mensaje' => 'Usuarios listados correctamente.',
            'OK' => true,
            'usuarios' => $usuarios
        ], 200);
    }

    // 2. REGISTRAR USUARIO
    public function registrarUsuario(Request $request): JsonResponse
    {
        $request->validate([
            'Nombre'          => 'required|string|max:100',
            'Apellido'        => 'required|string|max:100',
            'Direccion_envio' => 'nullable|string',
            'Telefono'        => 'nullable|string|max:30',
            'Email'           => 'nullable|email|max:190',
        ], [
            'Nombre.required' => 'El nombre es obligatorio.',
            'Nombre.max' => 'El nombre no debe superar 100 caracteres.',
            'Apellido.required' => 'El apellido es obligatorio.',
            'Apellido.max' => 'El apellido no debe superar 100 caracteres.',
            'Telefono.max' => 'El teléfono no debe superar 30 caracteres.',
            'Email.email' => 'El correo no tiene un formato válido.',
            'Email.max' => 'El correo no debe superar 190 caracteres.',
        ]);

        $usuario = Usuario::create($request->all());

        return response()->json([
            'mensaje' => 'Registro Correcto.',
            'OK' => true,
            'usuario' => $usuario
        ], 201);
    }

    // 3. MOSTRAR DETALLE DE UN USUARIO
    public function show($id): JsonResponse
    {
        $usuario = Usuario::find($id);

        if (!$usuario) {
            return response()->json([
                'mensaje' => 'Usuario no encontrado.',
                'OK' => false,
            ], 404);
        }

        return response()->json([
            'mensaje' => 'Usuario encontrado.',
            'OK' => true,
            'usuario' => $usuario
        ], 200);
    }

    // 4. ACTUALIZAR DATOS DEL USUARIO
    public function actualizar(Request $request, $id): JsonResponse
    {
        $usuario = Usuario::find($id);

        if (!$usuario) {
            return response()->json([
                'mensaje' => 'Usuario no encontrado.',
                'OK' => false,
            ], 404);
        }

        $request->validate([
            'Nombre'          => 'sometimes|string|max:100',
            'Apellido'        => 'sometimes|string|max:100',
            'Direccion_envio' => 'nullable|string',
            'Telefono'        => 'nullable|string|max:30',
            'Email'           => 'nullable|email|max:190',
        ], [
            'Nombre.max' => 'El nombre no debe superar 100 caracteres.',
            'Apellido.max' => 'El apellido no debe superar 100 caracteres.',
            'Telefono.max' => 'El teléfono no debe superar 30 caracteres.',
            'Email.email' => 'El correo no tiene un formato válido.',
            'Email.max' => 'El correo no debe superar 190 caracteres.',
        ]);

        $usuario->update($request->all());

        return response()->json([
            'mensaje' => 'Usuario actualizado correctamente.',
            'OK' => true,
            'usuario' => $usuario
        ], 200);
    }

    // 5. BORRAR USUARIO
    public function borrar($id): JsonResponse
    {
        $usuario = Usuario::find($id);

        if (!$usuario) {
            return response()->json([
                'mensaje' => 'Usuario no encontrado.',
                'OK' => false,
            ], 404);
        }

        $usuario->delete();
        
        return response()->json([
            'mensaje' => 'Usuario eliminado correctamente.',
            'OK' => true
        ], 200);
    }
}