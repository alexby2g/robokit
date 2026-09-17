<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            if (!Schema::hasColumn('users', 'role')) {
                Schema::table('users', fn (Blueprint $table) => $table->string('role', 20)->default('cliente')->after('password'));
            }
            if (!Schema::hasColumn('users', 'usuario_id')) {
                Schema::table('users', fn (Blueprint $table) => $table->unsignedInteger('usuario_id')->nullable()->unique()->after('role'));
            }
            if (!Schema::hasColumn('users', 'is_active')) {
                Schema::table('users', fn (Blueprint $table) => $table->boolean('is_active')->default(true)->after('usuario_id'));
            }
        }

        if (Schema::hasTable('usuario') && !Schema::hasColumn('usuario', 'Email')) {
            Schema::table('usuario', fn (Blueprint $table) => $table->string('Email', 190)->nullable()->unique()->after('Telefono'));
        }

        if (Schema::hasTable('producto')) {
            if (!Schema::hasColumn('producto', 'slug')) {
                Schema::table('producto', fn (Blueprint $table) => $table->string('slug', 180)->nullable()->unique()->after('Nombre'));
            }
            if (!Schema::hasColumn('producto', 'precio_anterior')) {
                Schema::table('producto', fn (Blueprint $table) => $table->decimal('precio_anterior', 10, 2)->nullable()->after('Precio'));
            }
            if (!Schema::hasColumn('producto', 'estado_publicacion')) {
                Schema::table('producto', fn (Blueprint $table) => $table->string('estado_publicacion', 20)->default('Publicado')->after('Descripcion'));
            }
            if (!Schema::hasColumn('producto', 'destacado')) {
                Schema::table('producto', fn (Blueprint $table) => $table->boolean('destacado')->default(false)->after('estado_publicacion'));
            }
            if (!Schema::hasColumn('producto', 'orden_catalogo')) {
                Schema::table('producto', fn (Blueprint $table) => $table->integer('orden_catalogo')->default(0)->after('destacado'));
            }

            foreach (DB::table('producto')->select('id', 'Nombre', 'slug')->get() as $producto) {
                if (!empty($producto->slug)) continue;
                $base = Str::slug($producto->Nombre) ?: 'producto';
                DB::table('producto')->where('id', $producto->id)->update([
                    'slug' => $base . '-' . $producto->id,
                ]);
            }
        }

        if (Schema::hasTable('imagenes')) {
            if (!Schema::hasColumn('imagenes', 'es_principal')) {
                Schema::table('imagenes', fn (Blueprint $table) => $table->boolean('es_principal')->default(false)->after('id_producto'));
            }
            if (!Schema::hasColumn('imagenes', 'orden')) {
                Schema::table('imagenes', fn (Blueprint $table) => $table->integer('orden')->default(0)->after('es_principal'));
            }

            $productoIds = DB::table('imagenes')->select('id_producto')->whereNotNull('id_producto')->distinct()->pluck('id_producto');
            foreach ($productoIds as $productoId) {
                $principal = DB::table('imagenes')->where('id_producto', $productoId)->orderBy('id')->value('id');
                if ($principal) DB::table('imagenes')->where('id', $principal)->update(['es_principal' => true]);
            }
        }

        if (!Schema::hasTable('catalogo_config')) {
            Schema::create('catalogo_config', function (Blueprint $table) {
                $table->id();
                $table->string('nombre_tienda', 120)->default('ROBOKIT STORE');
                $table->string('subtitulo', 180)->default('Robótica, kits y componentes');
                $table->string('hero_titulo', 220)->default('Robótica lista para tu próximo proyecto.');
                $table->text('hero_texto')->nullable();
                $table->string('whatsapp', 30)->nullable();
                $table->boolean('mostrar_stock')->default(true);
                $table->boolean('delivery_habilitado')->default(true);
                $table->boolean('recojo_habilitado')->default(true);
                $table->timestamps();
            });

            DB::table('catalogo_config')->insert([
                'id' => 1,
                'nombre_tienda' => 'ROBOKIT STORE',
                'subtitulo' => 'Robótica, kits y componentes',
                'hero_titulo' => 'Robótica lista para tu próximo proyecto.',
                'hero_texto' => 'Explora kits, placas, sensores y componentes. Haz tu pedido y sigue el estado desde la web.',
                'mostrar_stock' => true,
                'delivery_habilitado' => true,
                'recojo_habilitado' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogo_config');

        if (Schema::hasTable('imagenes')) {
            $drop = array_values(array_filter(['es_principal', 'orden'], fn ($c) => Schema::hasColumn('imagenes', $c)));
            if ($drop) Schema::table('imagenes', fn (Blueprint $table) => $table->dropColumn($drop));
        }

        if (Schema::hasTable('producto')) {
            $drop = array_values(array_filter(['slug', 'precio_anterior', 'estado_publicacion', 'destacado', 'orden_catalogo'], fn ($c) => Schema::hasColumn('producto', $c)));
            if ($drop) Schema::table('producto', fn (Blueprint $table) => $table->dropColumn($drop));
        }

        if (Schema::hasTable('usuario') && Schema::hasColumn('usuario', 'Email')) {
            Schema::table('usuario', fn (Blueprint $table) => $table->dropColumn('Email'));
        }

        if (Schema::hasTable('users')) {
            $drop = array_values(array_filter(['role', 'usuario_id', 'is_active'], fn ($c) => Schema::hasColumn('users', $c)));
            if ($drop) Schema::table('users', fn (Blueprint $table) => $table->dropColumn($drop));
        }
    }
};
