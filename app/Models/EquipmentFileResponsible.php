<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentFileResponsible extends Model
{
    protected $table = 'expediente_responsibles';

    protected $fillable = [
        'equipment_file_id',
        'trabajador_id',
        'display_name',
        'samaccountname',
        'mail',
        'source',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function equipmentFile(): BelongsTo
    {
        return $this->belongsTo(EquipmentFile::class, 'equipment_file_id');
    }

    public function trabajador(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Trabajador::class, 'trabajador_id');
    }
}
