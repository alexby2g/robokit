<?php

namespace App\Http\Controllers;

use App\Models\Curso;
use App\Models\Modulo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ModuloController extends Controller
{
    public function listar(): JsonResponse
    {
        $modulos = Modulo::with('curso')->orderBy('id_curso')->orderBy('orden')->orderBy('id')->get();
        return response()->json(['OK' => true, 'modulos' => $modulos]);
    }

    public function registrar(Request $request): JsonResponse
    {
        $data = $this->validateModule($request);
        if (empty($data['orden'])) {
            $data['orden'] = ((int) Modulo::where('id_curso', $data['id_curso'])->max('orden')) + 1;
        }
        $modulo = Modulo::create($data);
        return response()->json(['mensaje' => 'Módulo creado correctamente.', 'OK' => true, 'modulo' => $modulo], 201);
    }

    public function show($id): JsonResponse
    {
        $modulo = Modulo::with('curso')->find($id);
        if (!$modulo) return response()->json(['mensaje' => 'Módulo no encontrado.', 'OK' => false], 404);
        return response()->json(['OK' => true, 'modulo' => $modulo]);
    }

    public function actualizar(Request $request, $id): JsonResponse
    {
        $modulo = Modulo::find($id);
        if (!$modulo) return response()->json(['mensaje' => 'Módulo no encontrado.', 'OK' => false], 404);

        $data = $this->validateModule($request);
        $modulo->update($data);
        return response()->json(['mensaje' => 'Módulo actualizado correctamente.', 'OK' => true, 'modulo' => $modulo->fresh()]);
    }

    public function borrar($id): JsonResponse
    {
        $modulo = Modulo::find($id);
        if (!$modulo) return response()->json(['mensaje' => 'Módulo no encontrado.', 'OK' => false], 404);
        $courseId = $modulo->id_curso;
        $modulo->delete();
        $this->normalizeOrder($courseId);
        return response()->json(['mensaje' => 'Módulo eliminado correctamente.', 'OK' => true]);
    }

    public function listarPorCurso($idCurso): JsonResponse
    {
        $curso = Curso::find($idCurso);
        if (!$curso) return response()->json(['mensaje' => 'Capacitación no encontrada.', 'OK' => false], 404);
        $modulos = Modulo::where('id_curso', $idCurso)->orderBy('orden')->orderBy('id')->get();
        return response()->json(['OK' => true, 'curso' => $curso, 'modulos' => $modulos]);
    }

    public function reordenar(Request $request, int $idCurso): JsonResponse
    {
        abort_if(!Curso::whereKey($idCurso)->exists(), 404, 'Capacitación no encontrada.');
        $data = $request->validate([
            'modulos' => ['required', 'array', 'min:1'],
            'modulos.*' => ['required', 'integer'],
        ], ['modulos.required' => 'Debes enviar el orden de los módulos.']);

        $currentIds = Modulo::where('id_curso', $idCurso)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $requested = array_values(array_unique(array_map('intval', $data['modulos'])));
        $requestedSorted = $requested;
        sort($requestedSorted);

        if ($currentIds !== $requestedSorted) {
            throw ValidationException::withMessages(['modulos' => ['El orden enviado no coincide con los módulos del curso.']]);
        }

        DB::transaction(function () use ($requested, $idCurso) {
            foreach ($requested as $index => $moduleId) {
                Modulo::where('id_curso', $idCurso)->where('id', $moduleId)->update(['orden' => $index + 1]);
            }
        });

        return response()->json(['OK' => true, 'mensaje' => 'Orden de módulos actualizado.']);
    }

    private function validateModule(Request $request): array
    {
        return $request->validate([
            'id_curso' => ['required', 'integer', 'exists:cursos,id'],
            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'contenido' => ['nullable', 'string', 'max:20000'],
            'pasos' => ['nullable', 'string', 'max:20000'],
            'consejos' => ['nullable', 'string', 'max:10000'],
            'problemas_comunes' => ['nullable', 'string', 'max:10000'],
            'video' => ['nullable', 'string', 'max:500'],
            'material' => ['nullable', 'string', 'max:500'],
            'estado' => ['required', Rule::in(['activo', 'inactivo'])],
            'orden' => ['nullable', 'integer', 'min:1'],
        ], [
            'id_curso.required' => 'La capacitación es obligatoria.',
            'titulo.required' => 'El título del módulo es obligatorio.',
            'titulo.max' => 'El título no debe superar 255 caracteres.',
            'descripcion.max' => 'El resumen no debe superar 1000 caracteres.',
            'contenido.max' => 'El contenido del módulo es demasiado largo.',
            'pasos.max' => 'Los pasos del módulo son demasiado largos.',
            'consejos.max' => 'Los consejos del módulo son demasiado largos.',
            'problemas_comunes.max' => 'La solución de problemas es demasiado larga.',
            'video.max' => 'El enlace del video es demasiado largo.',
            'material.max' => 'El enlace del material es demasiado largo.',
            'estado.required' => 'Debes indicar si el módulo estará visible.',
        ]);
    }

    private function normalizeOrder(int $courseId): void
    {
        $ids = Modulo::where('id_curso', $courseId)->orderBy('orden')->orderBy('id')->pluck('id');
        foreach ($ids as $index => $id) {
            Modulo::whereKey($id)->update(['orden' => $index + 1]);
        }
    }
}
