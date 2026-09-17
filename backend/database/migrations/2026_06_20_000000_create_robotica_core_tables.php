<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('categoria')) {
            Schema::create('categoria', function (Blueprint $table) {
                $table->id();
                $table->string('Nombre', 100);
                $table->text('Descripcion')->nullable();
            });
        }

        if (!Schema::hasTable('usuario')) {
            Schema::create('usuario', function (Blueprint $table) {
                $table->id();
                $table->string('Nombre', 100);
                $table->string('Apellido', 100);
                $table->text('Direccion_envio')->nullable();
                $table->string('Telefono', 30)->nullable();
            });
        }

        if (!Schema::hasTable('producto')) {
            Schema::create('producto', function (Blueprint $table) {
                $table->id();
                $table->string('Nombre', 100);
                $table->decimal('Precio', 12, 2)->default(0);
                $table->integer('Stock')->default(0);
                $table->text('Descripcion')->nullable();
                $table->foreignId('id_categoria')->nullable()->constrained('categoria')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('imagenes')) {
            Schema::create('imagenes', function (Blueprint $table) {
                $table->id();
                $table->string('ruta', 500)->nullable();
                $table->foreignId('id_producto')->nullable()->constrained('producto')->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('pedido')) {
            Schema::create('pedido', function (Blueprint $table) {
                $table->id();
                $table->foreignId('id_usuario')->constrained('usuario')->cascadeOnDelete();
                $table->decimal('Total', 12, 2)->default(0);
                $table->string('Estado', 50)->default('Pendiente');
                $table->date('Fecha')->nullable();
            });
        }

        if (!Schema::hasTable('movimiento_stock')) {
            Schema::create('movimiento_stock', function (Blueprint $table) {
                $table->id();
                $table->foreignId('id_producto')->constrained('producto')->cascadeOnDelete();
                $table->string('tipo', 20);
                $table->unsignedInteger('cantidad');
                $table->integer('stock_anterior');
                $table->integer('stock_nuevo');
                $table->string('motivo', 255)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('cursos')) {
            Schema::create('cursos', function (Blueprint $table) {
                $table->id();
                $table->string('titulo', 255);
                $table->text('descripcion')->nullable();
                $table->string('imagen', 500)->nullable();
                $table->string('estado', 20)->default('activo');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('modulos')) {
            Schema::create('modulos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('id_curso')->constrained('cursos')->cascadeOnDelete();
                $table->string('titulo', 255);
                $table->text('descripcion')->nullable();
                $table->string('video', 500)->nullable();
                $table->string('material', 500)->nullable();
                $table->unsignedInteger('orden')->default(1);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('modulos');
        Schema::dropIfExists('cursos');
        Schema::dropIfExists('movimiento_stock');
        Schema::dropIfExists('pedido');
        Schema::dropIfExists('imagenes');
        Schema::dropIfExists('producto');
        Schema::dropIfExists('usuario');
        Schema::dropIfExists('categoria');
    }
};
