<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('modulos')) {
            if (!Schema::hasColumn('modulos', 'contenido')) {
                Schema::table('modulos', function (Blueprint $table) {
                    $table->longText('contenido')->nullable()->after('descripcion');
                });
            }
            if (!Schema::hasColumn('modulos', 'estado')) {
                Schema::table('modulos', function (Blueprint $table) {
                    $table->string('estado', 20)->default('activo')->after('material');
                });
            }
        }

        if (!Schema::hasTable('curso_producto')) {
            Schema::create('curso_producto', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('curso_id');
                $table->integer('producto_id');
                $table->timestamps();
                $table->unique(['curso_id', 'producto_id']);
                $table->index('producto_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('curso_producto');

        if (Schema::hasTable('modulos')) {
            $columns = [];
            if (Schema::hasColumn('modulos', 'contenido')) $columns[] = 'contenido';
            if (Schema::hasColumn('modulos', 'estado')) $columns[] = 'estado';
            if ($columns) {
                Schema::table('modulos', function (Blueprint $table) use ($columns) {
                    $table->dropColumn($columns);
                });
            }
        }
    }
};
