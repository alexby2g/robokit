<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    protected $table = 'producto';

    public $timestamps = false;

    protected $fillable = [
        'Nombre',
        'Precio',
        'Stock',
        'Descripcion',
        'id_categoria'
    ];

    public function imagenes()
    {
        return $this->hasMany(
            Imagen::class,
            'id_producto',
            'id'
        );
    }

    public function cursos()
    {
        return $this->belongsToMany(Curso::class, 'curso_producto', 'producto_id', 'curso_id');
    }

    public function movimientosStock()
    {
        return $this->hasMany(
            MovimientoStock::class,
            'id_producto',
            'id'
        );
    }
}
