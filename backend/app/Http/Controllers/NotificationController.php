<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $items = DB::table('notificaciones')
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->limit(40)
            ->get()
            ->map(function ($n) {
                $n->data = $n->data ? json_decode($n->data, true) : null;
                return $n;
            });

        $unread = DB::table('notificaciones')->where('user_id', $user->id)->whereNull('leida_at')->count();
        return response()->json(['notificaciones' => $items, 'no_leidas' => $unread]);
    }

    public function markRead(Request $request, int $id)
    {
        $updated = DB::table('notificaciones')->where('id', $id)->where('user_id', $request->user()->id)->update([
            'leida_at' => now(), 'updated_at' => now(),
        ]);
        abort_if(!$updated, 404, 'Notificación no encontrada.');
        return response()->json(['message' => 'Notificación marcada como leída.']);
    }

    public function markAll(Request $request)
    {
        DB::table('notificaciones')->where('user_id', $request->user()->id)->whereNull('leida_at')->update([
            'leida_at' => now(), 'updated_at' => now(),
        ]);
        return response()->json(['message' => 'Notificaciones marcadas como leídas.']);
    }
}
