<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pedido extends Model
{
    protected $table = 'pedido'; // Forzamos el uso de la tabla en singular
    public $timestamps = false;  

    protected $fillable = ['id_usuario', 'Total', 'Estado', 'Fecha'];

    // Relación inversa: Un pedido pertenece a un Usuario
    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id');
    }

    // Relación Muchos a Muchos: Un pedido tiene muchos productos y viceversa
    public function productos()
{
    // Le decimos: 1. El modelo destino, 2. El nombre de la tabla pivote, 3. La FK local, 4. La FK destino
    return $this->belongsToMany(Producto::class, 'pedido_producto', 'id_pedido', 'id_producto')
                ->withPivot('cantidad');
}
}