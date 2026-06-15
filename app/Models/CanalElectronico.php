<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CanalElectronico extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'canales_electronicos';

    protected $fillable = ['nombre', 'datacell_id', 'estado'];

    protected $casts = [
        'estado'      => 'boolean',
        'datacell_id' => 'integer',
    ];

    public function sources()
    {
        return $this->hasMany(DatacellSource::class, 'canal_electronico_id');
    }
}
