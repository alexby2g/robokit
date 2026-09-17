<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Categoria extends Model
{
    protected $table = 'categoria'; // En singular
    public $timestamps = false;     // Evita el error 500
    protected $fillable = ['Nombre', 'Descripcion'];
}