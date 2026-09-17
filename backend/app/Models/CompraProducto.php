<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompraProducto extends Model
{
    protected $table = 'compra_producto';
    public $timestamps = false;

    protected $fillable = [
        'id_compra', 'id_producto', 'cantidad', 'precio_unitario', 'subtotal'
    ];
}
