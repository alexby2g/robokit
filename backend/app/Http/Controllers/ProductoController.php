<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Services\MediaStorage;

class ProductoController extends Controller
{
    public function __construct(private MediaStorage $media) {}

    public function index()
    {
        $productos = DB::table('producto')->orderByDesc('id')->get();
        $this->attachProductMeta($productos);
        return response()->json(['productos' => $productos]);
    }

    public function show(int $id)
    {
        $producto = DB::table('producto')->where('id', $id)->first();
        abort_if(!$producto, 404, 'Producto no encontrado.');
        $productos = collect([$producto]);
        $this->attachProductMeta($productos);
        return response()->json(['producto' => $productos->first()]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $id = DB::transaction(function () use ($data, $request) {
            $id = (int) DB::table('producto')->insertGetId([
                'Nombre' => $data['Nombre'],
                'slug' => $this->uniqueSlug($data['Nombre']),
                'Precio' => $data['Precio'],
                'precio_anterior' => $data['precio_anterior'] ?? null,
                'Stock' => $data['Stock'],
                'stock_reservado' => 0,
                'Descripcion' => $data['Descripcion'] ?? null,
                'estado_publicacion' => $data['estado_publicacion'] ?? 'Borrador',
                'destacado' => (bool) ($data['destacado'] ?? false),
                'orden_catalogo' => (int) ($data['orden_catalogo'] ?? 0),
                'id_categoria' => $data['id_categoria'] ?? null,
            ]);

            $this->saveImages($request, $id);

            if ((int) $data['Stock'] > 0) {
                DB::table('movimiento_stock')->insert([
                    'id_producto' => $id,
                    'tipo' => 'entrada',
                    'cantidad' => (int) $data['Stock'],
                    'stock_anterior' => 0,
                    'stock_nuevo' => (int) $data['Stock'],
                    'motivo' => 'Stock inicial del producto',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $id;
        });

        return response()->json(['message' => 'Producto creado.', 'id' => $id], 201);
    }

    public function update(Request $request, int $id)
    {
        abort_if(!DB::table('producto')->where('id', $id)->exists(), 404, 'Producto no encontrado.');
        $data = $this->validateData($request);

        DB::transaction(function () use ($data, $request, $id) {
            $actual = DB::table('producto')->where('id', $id)->lockForUpdate()->first();
            $nombreCambio = trim((string) $actual->Nombre) !== trim((string) $data['Nombre']);

            DB::table('producto')->where('id', $id)->update([
                'Nombre' => $data['Nombre'],
                'slug' => $nombreCambio ? $this->uniqueSlug($data['Nombre'], $id) : $actual->slug,
                'Precio' => $data['Precio'],
                'precio_anterior' => $data['precio_anterior'] ?? null,
                'Stock' => $actual->Stock,
                'Descripcion' => $data['Descripcion'] ?? null,
                'estado_publicacion' => $data['estado_publicacion'] ?? 'Borrador',
                'destacado' => (bool) ($data['destacado'] ?? false),
                'orden_catalogo' => (int) ($data['orden_catalogo'] ?? 0),
                'id_categoria' => $data['id_categoria'] ?? null,
            ]);

            $deleteIds = $request->input('eliminar_imagenes', []);
            if (is_string($deleteIds)) {
                $deleteIds = array_filter(array_map('intval', explode(',', $deleteIds)));
            }
            foreach ((array) $deleteIds as $imageId) {
                $this->deleteImageRecord($id, (int) $imageId);
            }

            $this->saveImages($request, $id);

            $principalId = (int) $request->input('imagen_principal_id', 0);
            if ($principalId > 0) $this->setMainImageInternal($id, $principalId);
        });

        return response()->json(['message' => 'Producto actualizado y catálogo sincronizado.']);
    }

    public function setMainImage(int $id, int $imagenId)
    {
        abort_if(!DB::table('producto')->where('id', $id)->exists(), 404, 'Producto no encontrado.');
        $this->setMainImageInternal($id, $imagenId);
        return response()->json(['message' => 'Imagen principal actualizada.']);
    }

    public function deleteImage(int $id, int $imagenId)
    {
        $this->deleteImageRecord($id, $imagenId);
        $this->ensureMainImage($id);
        return response()->json(['message' => 'Imagen eliminada.']);
    }

    public function destroy(int $id)
    {
        if (DB::table('pedido_producto')->where('id_producto', $id)->exists() ||
            (Schema::hasTable('compra_producto') && DB::table('compra_producto')->where('id_producto', $id)->exists())) {
            return response()->json(['message' => 'No se puede eliminar un producto con historial. Puedes ocultarlo del catálogo.'], 422);
        }

        $paths = DB::table('imagenes')->where('id_producto', $id)->pluck('ruta');
        DB::transaction(function () use ($id) {
            DB::table('imagenes')->where('id_producto', $id)->delete();
            DB::table('producto')->where('id', $id)->delete();
        });
        foreach ($paths as $path) $this->media->deletePublic($path);

        return response()->json(['message' => 'Producto eliminado.']);
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'Nombre' => ['required', 'string', 'max:100'],
            'Precio' => ['required', 'numeric', 'min:0'],
            'precio_anterior' => ['nullable', 'numeric', 'min:0'],
            'Stock' => ['required', 'integer', 'min:0'],
            'Descripcion' => ['nullable', 'string'],
            'id_categoria' => ['required', 'integer', 'exists:categoria,id'],
            'estado_publicacion' => ['nullable', Rule::in(['Borrador', 'Publicado', 'Oculto'])],
            'destacado' => ['nullable', 'boolean'],
            'orden_catalogo' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'imagen' => ['nullable', 'file', 'max:12288'],
            'imagenes' => ['nullable', 'array', 'max:8'],
            'imagenes.*' => ['file', 'max:12288'],
            'eliminar_imagenes' => ['nullable'],
            'imagen_principal_id' => ['nullable', 'integer'],
        ], [
            'Nombre.required' => 'El nombre del producto es obligatorio.',
            'Nombre.max' => 'El nombre del producto no debe superar 100 caracteres.',
            'Precio.required' => 'El precio es obligatorio.',
            'Precio.numeric' => 'El precio debe ser numérico.',
            'Precio.min' => 'El precio no puede ser negativo.',
            'precio_anterior.numeric' => 'El precio anterior debe ser numérico.',
            'precio_anterior.min' => 'El precio anterior no puede ser negativo.',
            'Stock.required' => 'El stock es obligatorio.',
            'Stock.integer' => 'El stock debe ser un número entero.',
            'Stock.min' => 'El stock no puede ser negativo.',
            'id_categoria.required' => 'Debes seleccionar una categoría.',
            'id_categoria.exists' => 'La categoría seleccionada no existe.',
            'estado_publicacion.in' => 'El estado de publicación no es válido.',
            'orden_catalogo.integer' => 'El orden del catálogo debe ser un número entero.',
            'orden_catalogo.min' => 'El orden del catálogo no puede ser negativo.',
            'imagenes.max' => 'Puedes seleccionar como máximo 8 imágenes.',
            'imagenes.*.max' => 'Cada imagen debe pesar como máximo 12 MB.',
        ]);
    }

    private function saveImages(Request $request, int $productoId): void
    {
        $files = [];
        if ($request->hasFile('imagen')) $files[] = $request->file('imagen');
        if ($request->hasFile('imagenes')) {
            $incoming = $request->file('imagenes');
            $files = array_merge($files, is_array($incoming) ? $incoming : [$incoming]);
        }
        if (!$files) return;

        $existingCount = DB::table('imagenes')->where('id_producto', $productoId)->count();
        if (($existingCount + count($files)) > 8) {
            throw ValidationException::withMessages(['imagenes' => 'Cada producto puede tener hasta 8 imágenes.']);
        }

        $order = (int) DB::table('imagenes')->where('id_producto', $productoId)->max('orden');
        foreach ($files as $file) {
            if (!$file || !$file->isValid()) {
                throw ValidationException::withMessages(['imagenes' => 'Una de las imágenes no se pudo subir.']);
            }

            $extension = strtolower($file->getClientOriginalExtension() ?: '');
            $allowed = ['jpg', 'jpeg', 'jfif', 'png', 'webp', 'gif', 'bmp', 'avif', 'heic', 'heif', 'tif', 'tiff'];
            if (!in_array($extension, $allowed, true)) {
                throw ValidationException::withMessages(['imagenes' => 'Formato de imagen no permitido.']);
            }

            $mime = strtolower((string) $file->getMimeType());
            $modernFallback = ['avif', 'heic', 'heif'];
            if (!str_starts_with($mime, 'image/') && !($mime === 'application/octet-stream' && in_array($extension, $modernFallback, true))) {
                throw ValidationException::withMessages(['imagenes' => 'El archivo seleccionado no parece ser una imagen válida.']);
            }

            $filename = Str::uuid()->toString().'.'.$extension;
            $path = $this->media->storePublicAs($file, 'productos', $filename);
            $isFirst = DB::table('imagenes')->where('id_producto', $productoId)->doesntExist();
            DB::table('imagenes')->insert([
                'ruta' => $path,
                'id_producto' => $productoId,
                'es_principal' => $isFirst,
                'orden' => ++$order,
            ]);
        }
    }

    private function deleteImageRecord(int $productoId, int $imagenId): void
    {
        $image = DB::table('imagenes')->where('id', $imagenId)->where('id_producto', $productoId)->first();
        if (!$image) return;
        DB::table('imagenes')->where('id', $imagenId)->delete();
        if ($image->ruta) $this->media->deletePublic($image->ruta);
    }

    private function setMainImageInternal(int $productoId, int $imagenId): void
    {
        $exists = DB::table('imagenes')->where('id', $imagenId)->where('id_producto', $productoId)->exists();
        abort_if(!$exists, 404, 'Imagen no encontrada para este producto.');
        DB::table('imagenes')->where('id_producto', $productoId)->update(['es_principal' => false]);
        DB::table('imagenes')->where('id', $imagenId)->update(['es_principal' => true]);
    }

    private function ensureMainImage(int $productoId): void
    {
        if (DB::table('imagenes')->where('id_producto', $productoId)->where('es_principal', true)->exists()) return;
        $first = DB::table('imagenes')->where('id_producto', $productoId)->orderBy('orden')->orderBy('id')->value('id');
        if ($first) DB::table('imagenes')->where('id', $first)->update(['es_principal' => true]);
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'producto';
        $slug = $base;
        $counter = 2;
        while (DB::table('producto')->where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$counter++;
        }
        return $slug;
    }

    private function attachProductMeta($productos): void
    {
        $imagenes = DB::table('imagenes')
            ->orderByDesc('es_principal')
            ->orderBy('orden')
            ->orderBy('id')
            ->get()
            ->groupBy('id_producto');

        $categorias = DB::table('categoria')->pluck('Nombre', 'id');
        foreach ($productos as $producto) {
            $reservado = (int) ($producto->stock_reservado ?? 0);
            $producto->Stock_reservado = $reservado;
            $producto->Disponible = max(0, (int) $producto->Stock - $reservado);
            $producto->categoria_nombre = $categorias[$producto->id_categoria] ?? null;
            $producto->imagenes = ($imagenes[$producto->id] ?? collect())->values();
        }
    }
}
