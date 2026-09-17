<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('compra')) {
            Schema::create('compra', function (Blueprint $table) {
                $table->increments('id');
                $table->string('Proveedor', 150);
                $table->string('Documento', 80)->nullable();
                $table->decimal('Total', 10, 2)->default(0);
                $table->date('Fecha');
                $table->text('Observacion')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('compra_producto')) {
            Schema::create('compra_producto', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->integer('id_compra');
                $table->integer('id_producto');
                $table->integer('cantidad');
                $table->decimal('precio_unitario', 10, 2);
                $table->decimal('subtotal', 10, 2);
                $table->index('id_compra');
                $table->index('id_producto');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('compra_producto');
        Schema::dropIfExists('compra');
    }
};
