<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MovimientoStock extends Model
{
    protected $table = 'movimiento_stock';

    public $timestamps = true;

    protected $fillable = [
        'id_producto',
        'tipo',
        'cantidad',
        'stock_anterior',
        'stock_nuevo',
        'motivo'
    ];

    public function producto()
    {
        return $this->belongsTo(
            Producto::class,
            'id_producto',
            'id'
        );
    }
}
