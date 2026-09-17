<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CatalogoController extends Controller
{
    public function show()
    {
        return response()->json(['config' => $this->config()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'nombre_tienda' => ['required', 'string', 'max:120'],
            'subtitulo' => ['nullable', 'string', 'max:180'],
            'hero_titulo' => ['required', 'string', 'max:220'],
            'hero_texto' => ['nullable', 'string', 'max:1000'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'mostrar_stock' => ['required', 'boolean'],
            'delivery_habilitado' => ['required', 'boolean'],
            'recojo_habilitado' => ['required', 'boolean'],
        ], [
            'nombre_tienda.required' => 'El nombre de la tienda es obligatorio.',
            'nombre_tienda.max' => 'El nombre de la tienda no debe superar 120 caracteres.',
            'hero_titulo.required' => 'El título principal es obligatorio.',
            'hero_titulo.max' => 'El título principal no debe superar 220 caracteres.',
            'subtitulo.max' => 'El subtítulo no debe superar 180 caracteres.',
            'hero_texto.max' => 'El texto principal no debe superar 1000 caracteres.',
            'whatsapp.max' => 'El WhatsApp no debe superar 30 caracteres.',
        ]);

        if (!$data['delivery_habilitado'] && !$data['recojo_habilitado']) {
            return response()->json(['message' => 'Debes habilitar al menos una forma de entrega.'], 422);
        }

        DB::table('catalogo_config')->updateOrInsert(
            ['id' => 1],
            [...$data, 'updated_at' => now()]
        );

        return response()->json([
            'message' => 'Catálogo actualizado en línea.',
            'config' => $this->config(),
        ]);
    }

    private function config()
    {
        return DB::table('catalogo_config')->where('id', 1)->first() ?? (object) [
            'nombre_tienda' => 'ROBOKIT STORE',
            'subtitulo' => 'Robótica, kits y componentes',
            'hero_titulo' => 'Robótica lista para tu próximo proyecto.',
            'hero_texto' => 'Explora kits, placas, sensores y componentes.',
            'whatsapp' => null,
            'mostrar_stock' => true,
            'delivery_habilitado' => true,
            'recojo_habilitado' => true,
        ];
    }
}
