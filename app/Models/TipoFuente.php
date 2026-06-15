<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TipoFuente extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tipos_fuentes';

    protected $fillable = ['nombre', 'datacell_id', 'estado'];

    protected $casts = [
        'estado'      => 'boolean',
        'datacell_id' => 'integer',
    ];

    public function sources()
    {
        return $this->hasMany(DatacellSource::class, 'tipo_fuente_id');
    }
}
