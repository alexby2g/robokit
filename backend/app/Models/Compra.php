<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Compra extends Model
{
    protected $table = 'compra';
    public $timestamps = true;

    protected $fillable = [
        'Proveedor', 'Documento', 'Total', 'Fecha', 'Observacion'
    ];

    public function productos()
    {
        return $this->belongsToMany(Producto::class, 'compra_producto', 'id_compra', 'id_producto')
            ->withPivot(['cantidad', 'precio_unitario', 'subtotal']);
    }
}
