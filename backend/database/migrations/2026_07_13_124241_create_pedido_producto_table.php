<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pedido_producto', function (Blueprint $table) {
            $table->id();
            
            // Llave foránea hacia la tabla 'pedido'
            $table->foreignId('id_pedido')
                  ->constrained('pedido') // Indica explícitamente el nombre de la tabla en singular
                  ->onDelete('cascade');  // Si se borra el pedido, se limpia su detalle

            // Llave foránea hacia la tabla 'producto'
            $table->foreignId('id_producto')
                  ->constrained('producto') // Indica explícitamente el nombre de la tabla en singular
                  ->onDelete('cascade');

            // Columna adicional para guardar cuántas unidades de ese kit se compraron
            $table->integer('cantidad'); 

            // Opcional: Si quieres llevar control de cuándo se agregó el producto al carrito
            // $table->timestamps(); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pedido_producto');
    }
};