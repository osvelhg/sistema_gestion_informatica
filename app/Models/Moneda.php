<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Moneda extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'monedas';

    protected $fillable = [
        'nombre',
        'sigla',
        'simbolo',
        'tasa_cambio',
        'estado',
    ];

    protected $casts = [
        'estado'      => 'boolean',
        'tasa_cambio' => 'decimal:4',
    ];
}
