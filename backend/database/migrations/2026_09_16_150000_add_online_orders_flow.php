<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('producto') && !Schema::hasColumn('producto', 'stock_reservado')) {
            Schema::table('producto', function (Blueprint $table) {
                $table->unsignedInteger('stock_reservado')->default(0)->after('Stock');
            });
        }

        if (Schema::hasTable('pedido')) {
            Schema::table('pedido', function (Blueprint $table) {
                if (!Schema::hasColumn('pedido', 'canal')) $table->string('canal', 20)->default('Mostrador');
                if (!Schema::hasColumn('pedido', 'tipo_entrega')) $table->string('tipo_entrega', 30)->nullable();
                if (!Schema::hasColumn('pedido', 'direccion_entrega')) $table->text('direccion_entrega')->nullable();
                if (!Schema::hasColumn('pedido', 'notas_cliente')) $table->text('notas_cliente')->nullable();
                if (!Schema::hasColumn('pedido', 'codigo_seguimiento')) $table->string('codigo_seguimiento', 40)->nullable()->unique();
                if (!Schema::hasColumn('pedido', 'reserva_aplicada')) $table->boolean('reserva_aplicada')->default(false);
                if (!Schema::hasColumn('pedido', 'fecha_entregado')) $table->timestamp('fecha_entregado')->nullable();
            });

            DB::table('pedido')->whereNull('canal')->orWhere('canal', '')->update(['canal' => 'Mostrador']);
            DB::table('pedido')->whereNull('tipo_entrega')->update(['tipo_entrega' => 'Mostrador']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pedido')) {
            Schema::table('pedido', function (Blueprint $table) {
                $drop = [];
                foreach (['canal','tipo_entrega','direccion_entrega','notas_cliente','codigo_seguimiento','reserva_aplicada','fecha_entregado'] as $col) {
                    if (Schema::hasColumn('pedido', $col)) $drop[] = $col;
                }
                if ($drop) $table->dropColumn($drop);
            });
        }
        if (Schema::hasTable('producto') && Schema::hasColumn('producto', 'stock_reservado')) {
            Schema::table('producto', fn (Blueprint $table) => $table->dropColumn('stock_reservado'));
        }
    }
};
