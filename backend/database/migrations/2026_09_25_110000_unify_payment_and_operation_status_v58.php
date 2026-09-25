<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('pedido') && !Schema::hasColumn('pedido', 'estado_operacion')) {
            Schema::table('pedido', function (Blueprint $table) {
                $table->string('estado_operacion', 20)->default('Activo');
            });
            DB::table('pedido')->whereIn('Estado', ['Entregado', 'Cancelado'])->update(['estado_operacion' => 'Finalizado']);
        }

        if (Schema::hasTable('compra') && !Schema::hasColumn('compra', 'estado_operacion')) {
            Schema::table('compra', function (Blueprint $table) {
                $table->string('estado_operacion', 20)->default('Activo');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pedido') && Schema::hasColumn('pedido', 'estado_operacion')) {
            Schema::table('pedido', fn (Blueprint $table) => $table->dropColumn('estado_operacion'));
        }
        if (Schema::hasTable('compra') && Schema::hasColumn('compra', 'estado_operacion')) {
            Schema::table('compra', fn (Blueprint $table) => $table->dropColumn('estado_operacion'));
        }
    }
};
