<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('pedido') && !Schema::hasColumn('pedido', 'metodo_pago')) {
            Schema::table('pedido', function (Blueprint $table) {
                $table->string('metodo_pago', 30)->nullable()->after('tipo_entrega');
            });
        }

        if (!Schema::hasTable('pagos')) {
            Schema::create('pagos', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('pedido_id')->unique();
                $table->unsignedInteger('usuario_id')->nullable();
                $table->string('metodo', 30)->default('Efectivo');
                $table->decimal('monto', 12, 2)->default(0);
                $table->string('estado', 30)->default('Pendiente');
                $table->string('referencia', 120)->nullable();
                $table->string('comprobante', 500)->nullable();
                $table->text('nota')->nullable();
                $table->unsignedBigInteger('verificado_por')->nullable();
                $table->timestamp('reportado_at')->nullable();
                $table->timestamp('verificado_at')->nullable();
                $table->timestamps();
                $table->index(['estado', 'created_at']);
            });
        }

        if (!Schema::hasTable('notificaciones')) {
            Schema::create('notificaciones', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('tipo', 50)->default('info');
                $table->string('titulo', 180);
                $table->text('mensaje')->nullable();
                $table->string('ruta', 255)->nullable();
                $table->json('data')->nullable();
                $table->timestamp('leida_at')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'leida_at']);
            });
        }

        if (Schema::hasTable('cursos')) {
            Schema::table('cursos', function (Blueprint $table) {
                if (!Schema::hasColumn('cursos', 'objetivo')) $table->text('objetivo')->nullable()->after('descripcion');
                if (!Schema::hasColumn('cursos', 'nivel')) $table->string('nivel', 50)->nullable()->after('objetivo');
                if (!Schema::hasColumn('cursos', 'duracion_minutos')) $table->unsignedInteger('duracion_minutos')->nullable()->after('nivel');
                if (!Schema::hasColumn('cursos', 'materiales')) $table->longText('materiales')->nullable()->after('duracion_minutos');
                if (!Schema::hasColumn('cursos', 'recomendaciones')) $table->longText('recomendaciones')->nullable()->after('materiales');
            });
        }

        if (Schema::hasTable('modulos')) {
            Schema::table('modulos', function (Blueprint $table) {
                if (!Schema::hasColumn('modulos', 'pasos')) $table->longText('pasos')->nullable()->after('contenido');
                if (!Schema::hasColumn('modulos', 'consejos')) $table->longText('consejos')->nullable()->after('pasos');
                if (!Schema::hasColumn('modulos', 'problemas_comunes')) $table->longText('problemas_comunes')->nullable()->after('consejos');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('modulos')) {
            $drop = array_values(array_filter(['pasos', 'consejos', 'problemas_comunes'], fn ($c) => Schema::hasColumn('modulos', $c)));
            if ($drop) Schema::table('modulos', fn (Blueprint $table) => $table->dropColumn($drop));
        }
        if (Schema::hasTable('cursos')) {
            $drop = array_values(array_filter(['objetivo', 'nivel', 'duracion_minutos', 'materiales', 'recomendaciones'], fn ($c) => Schema::hasColumn('cursos', $c)));
            if ($drop) Schema::table('cursos', fn (Blueprint $table) => $table->dropColumn($drop));
        }
        Schema::dropIfExists('notificaciones');
        Schema::dropIfExists('pagos');
        if (Schema::hasTable('pedido') && Schema::hasColumn('pedido', 'metodo_pago')) {
            Schema::table('pedido', fn (Blueprint $table) => $table->dropColumn('metodo_pago'));
        }
    }
};
