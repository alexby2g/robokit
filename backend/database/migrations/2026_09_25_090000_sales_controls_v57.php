<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('pedido')) {
            Schema::table('pedido', function (Blueprint $table) {
                if (!Schema::hasColumn('pedido', 'evidencia_entrega')) {
                    $table->string('evidencia_entrega', 500)->nullable();
                }
                if (!Schema::hasColumn('pedido', 'evidencia_entrega_at')) {
                    $table->timestamp('evidencia_entrega_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('pedido')) return;
        Schema::table('pedido', function (Blueprint $table) {
            $drop = [];
            if (Schema::hasColumn('pedido', 'evidencia_entrega')) $drop[] = 'evidencia_entrega';
            if (Schema::hasColumn('pedido', 'evidencia_entrega_at')) $drop[] = 'evidencia_entrega_at';
            if ($drop) $table->dropColumn($drop);
        });
    }
};
