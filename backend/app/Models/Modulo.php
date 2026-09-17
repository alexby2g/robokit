<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Modulo extends Model
{
    protected $table = 'modulos';

    protected $fillable = [
        'id_curso',
        'titulo',
        'descripcion',
        'contenido',
        'pasos',
        'consejos',
        'problemas_comunes',
        'video',
        'material',
        'estado',
        'orden',
    ];

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class, 'id_curso');
    }
}
