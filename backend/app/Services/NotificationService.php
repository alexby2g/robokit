<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NotificationService
{
    public function staff(string $tipo, string $titulo, ?string $mensaje = null, ?string $ruta = null, array $data = []): void
    {
        if (!Schema::hasTable('notificaciones') || !Schema::hasTable('users')) return;

        $ids = DB::table('users')
            ->whereIn('role', ['admin', 'trabajador', 'caja', 'almacen'])
            ->where('is_active', 1)
            ->pluck('id');

        $rows = [];
        foreach ($ids as $id) {
            $rows[] = $this->row((int) $id, $tipo, $titulo, $mensaje, $ruta, $data);
        }
        if ($rows) DB::table('notificaciones')->insert($rows);
    }

    public function clientByUsuario(int $usuarioId, string $tipo, string $titulo, ?string $mensaje = null, ?string $ruta = null, array $data = []): void
    {
        if (!Schema::hasTable('notificaciones') || !Schema::hasTable('users')) return;
        $userId = DB::table('users')->where('role', 'cliente')->where('usuario_id', $usuarioId)->where('is_active', 1)->value('id');
        if (!$userId) return;
        DB::table('notificaciones')->insert($this->row((int) $userId, $tipo, $titulo, $mensaje, $ruta, $data));
    }

    private function row(int $userId, string $tipo, string $titulo, ?string $mensaje, ?string $ruta, array $data): array
    {
        return [
            'user_id' => $userId,
            'tipo' => $tipo,
            'titulo' => $titulo,
            'mensaje' => $mensaje,
            'ruta' => $ruta,
            'data' => $data ? json_encode($data, JSON_UNESCAPED_UNICODE) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
