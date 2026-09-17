<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Curso extends Model
{
    protected $table = 'cursos';

    protected $fillable = [
        'titulo',
        'descripcion',
        'objetivo',
        'nivel',
        'duracion_minutos',
        'materiales',
        'recomendaciones',
        'imagen',
        'estado',
    ];

    public function modulos(): HasMany
    {
        return $this->hasMany(Modulo::class, 'id_curso')->orderBy('orden')->orderBy('id');
    }

    public function productos(): BelongsToMany
    {
        return $this->belongsToMany(Producto::class, 'curso_producto', 'curso_id', 'producto_id')
            ->select('producto.id', 'producto.Nombre', 'producto.Precio', 'producto.estado_publicacion');
    }
}
