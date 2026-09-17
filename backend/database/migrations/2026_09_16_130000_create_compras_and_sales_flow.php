<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('compra')) {
            Schema::create('compra', function (Blueprint $table) {
                $table->id();
                $table->string('proveedor', 160);
                $table->string('nro_documento', 80)->nullable();
                $table->decimal('Total', 12, 2)->default(0);
                $table->date('Fecha')->nullable();
                $table->text('observacion')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('compra_producto')) {
            Schema::create('compra_producto', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('id_compra');
                $table->integer('id_producto');
                $table->unsignedInteger('cantidad');
                $table->decimal('costo_unitario', 12, 2);
                $table->decimal('subtotal', 12, 2);
                $table->timestamps();
                $table->index('id_compra');
                $table->index('id_producto');
            });
        }

        if (Schema::hasTable('pedido_producto')) {
            Schema::table('pedido_producto', function (Blueprint $table) {
                if (!Schema::hasColumn('pedido_producto', 'precio_unitario')) $table->decimal('precio_unitario', 12, 2)->nullable();
                if (!Schema::hasColumn('pedido_producto', 'subtotal')) $table->decimal('subtotal', 12, 2)->nullable();
            });
        }

        if (Schema::hasTable('pedido') && !Schema::hasColumn('pedido', 'stock_aplicado')) {
            Schema::table('pedido', function (Blueprint $table) {
                $table->boolean('stock_aplicado')->default(false);
            });
        }

        if (Schema::hasTable('pedido') && Schema::hasColumn('pedido', 'stock_aplicado')) {
            DB::table('pedido')->whereNotIn('Estado', ['Cancelado', 'cancelado', 'CANCELADO'])->update(['stock_aplicado' => 1]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pedido') && Schema::hasColumn('pedido', 'stock_aplicado')) {
            Schema::table('pedido', fn (Blueprint $table) => $table->dropColumn('stock_aplicado'));
        }
        if (Schema::hasTable('pedido_producto')) {
            Schema::table('pedido_producto', function (Blueprint $table) {
                $cols = [];
                if (Schema::hasColumn('pedido_producto', 'precio_unitario')) $cols[] = 'precio_unitario';
                if (Schema::hasColumn('pedido_producto', 'subtotal')) $cols[] = 'subtotal';
                if ($cols) $table->dropColumn($cols);
            });
        }
        Schema::dropIfExists('compra_producto');
        Schema::dropIfExists('compra');
    }
};
