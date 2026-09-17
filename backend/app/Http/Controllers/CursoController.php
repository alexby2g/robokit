<?php

namespace App\Http\Controllers;

use App\Models\Curso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CursoController extends Controller
{
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'jfif', 'png', 'webp', 'gif', 'bmp', 'avif', 'heic', 'heif', 'tif', 'tiff'];

    public function listar(): JsonResponse
    {
        return $this->listarPublicos();
    }

    public function listarPublicos(): JsonResponse
    {
        $cursos = Curso::query()
            ->where('estado', 'activo')
            ->with([
                'modulos' => fn ($q) => $q->where('estado', 'activo')->orderBy('orden')->orderBy('id'),
                'productos' => fn ($q) => $q->where('estado_publicacion', 'Publicado'),
            ])
            ->orderByDesc('id')
            ->get();

        return response()->json(['OK' => true, 'cursos' => $cursos]);
    }

    public function show($id): JsonResponse
    {
        return $this->showPublico($id);
    }

    public function showPublico($id): JsonResponse
    {
        $curso = Curso::query()
            ->where('estado', 'activo')
            ->with([
                'modulos' => fn ($q) => $q->where('estado', 'activo')->orderBy('orden')->orderBy('id'),
                'productos' => fn ($q) => $q->where('estado_publicacion', 'Publicado'),
            ])
            ->find($id);

        if (!$curso) {
            return response()->json(['mensaje' => 'Capacitación no disponible.', 'OK' => false], 404);
        }

        return response()->json(['OK' => true, 'curso' => $curso]);
    }

    public function adminIndex(): JsonResponse
    {
        $cursos = Curso::query()
            ->with(['modulos', 'productos'])
            ->orderByDesc('id')
            ->get();

        return response()->json(['OK' => true, 'cursos' => $cursos]);
    }

    public function adminShow($id): JsonResponse
    {
        $curso = Curso::with(['modulos', 'productos'])->find($id);
        if (!$curso) {
            return response()->json(['mensaje' => 'Capacitación no encontrada.', 'OK' => false], 404);
        }
        return response()->json(['OK' => true, 'curso' => $curso]);
    }

    public function registrar(Request $request): JsonResponse
    {
        $data = $this->validateCourse($request);
        $data['imagen'] = $this->resolveImage($request, $data['imagen'] ?? null, null);

        $curso = Curso::create($this->coursePayload($data));
        $this->syncProducts($curso, $data['productos'] ?? []);

        return response()->json([
            'mensaje' => 'Capacitación creada correctamente.',
            'OK' => true,
            'curso' => $curso->load(['modulos', 'productos']),
        ], 201);
    }

    public function actualizar(Request $request, $id): JsonResponse
    {
        $curso = Curso::find($id);
        if (!$curso) {
            return response()->json(['mensaje' => 'Capacitación no encontrada.', 'OK' => false], 404);
        }

        $data = $this->validateCourse($request);
        $data['imagen'] = $this->resolveImage($request, $data['imagen'] ?? $curso->imagen, $curso->imagen);

        $curso->update($this->coursePayload($data));
        $this->syncProducts($curso, $data['productos'] ?? []);

        return response()->json([
            'mensaje' => 'Capacitación actualizada correctamente.',
            'OK' => true,
            'curso' => $curso->fresh()->load(['modulos', 'productos']),
        ]);
    }

    public function borrar($id): JsonResponse
    {
        $curso = Curso::find($id);
        if (!$curso) {
            return response()->json(['mensaje' => 'Capacitación no encontrada.', 'OK' => false], 404);
        }

        $oldImage = $curso->imagen;
        $curso->productos()->detach();
        $curso->delete();
        $this->deleteStoredCourseImage($oldImage);

        return response()->json(['mensaje' => 'Capacitación eliminada correctamente.', 'OK' => true]);
    }

    private function validateCourse(Request $request): array
    {
        return $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:3000'],
            'objetivo' => ['nullable', 'string', 'max:3000'],
            'nivel' => ['nullable', 'string', 'max:50'],
            'duracion_minutos' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'materiales' => ['nullable', 'string', 'max:10000'],
            'recomendaciones' => ['nullable', 'string', 'max:10000'],
            'imagen' => ['nullable', 'string', 'max:500'],
            'imagen_archivo' => ['nullable', 'file', 'max:12288'],
            'estado' => ['required', Rule::in(['activo', 'inactivo'])],
            'productos' => ['nullable', 'array'],
            'productos.*' => ['integer', 'exists:producto,id'],
        ], [
            'titulo.required' => 'El título de la capacitación es obligatorio.',
            'titulo.max' => 'El título no debe superar 255 caracteres.',
            'descripcion.max' => 'La descripción no debe superar 3000 caracteres.',
            'objetivo.max' => 'El objetivo no debe superar 3000 caracteres.',
            'duracion_minutos.integer' => 'La duración debe indicarse en minutos.',
            'duracion_minutos.min' => 'La duración debe ser mayor a cero.',
            'imagen_archivo.max' => 'La imagen no debe superar 12 MB.',
            'estado.required' => 'Debes seleccionar el estado de la capacitación.',
            'estado.in' => 'El estado seleccionado no es válido.',
            'productos.*.exists' => 'Uno de los productos relacionados ya no existe.',
        ]);
    }

    private function resolveImage(Request $request, ?string $fallback, ?string $oldImage): ?string
    {
        $file = $request->file('imagen_archivo');
        if (!$file) return $fallback ?: null;

        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (!in_array($extension, self::IMAGE_EXTENSIONS, true)) {
            throw ValidationException::withMessages([
                'imagen_archivo' => ['Formato de imagen no permitido. Usa JPG, PNG, WEBP, AVIF, HEIC, GIF, BMP o TIFF.'],
            ]);
        }

        $path = $file->store('cursos', 'public');
        $this->deleteStoredCourseImage($oldImage);
        return $path;
    }

    private function deleteStoredCourseImage(?string $path): void
    {
        if ($path && str_starts_with($path, 'cursos/')) {
            Storage::disk('public')->delete($path);
        }
    }

    private function coursePayload(array $data): array
    {
        return [
            'titulo' => trim($data['titulo']),
            'descripcion' => isset($data['descripcion']) && trim((string) $data['descripcion']) !== '' ? trim($data['descripcion']) : null,
            'objetivo' => isset($data['objetivo']) && trim((string) $data['objetivo']) !== '' ? trim($data['objetivo']) : null,
            'nivel' => isset($data['nivel']) && trim((string) $data['nivel']) !== '' ? trim($data['nivel']) : null,
            'duracion_minutos' => $data['duracion_minutos'] ?? null,
            'materiales' => isset($data['materiales']) && trim((string) $data['materiales']) !== '' ? trim($data['materiales']) : null,
            'recomendaciones' => isset($data['recomendaciones']) && trim((string) $data['recomendaciones']) !== '' ? trim($data['recomendaciones']) : null,
            'imagen' => $data['imagen'] ?? null,
            'estado' => $data['estado'],
        ];
    }

    private function syncProducts(Curso $curso, array $productIds): void
    {
        $ids = array_values(array_unique(array_map('intval', $productIds)));
        $curso->productos()->sync($ids);
    }
}
