<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportControlRecord extends Model
{
    protected $table = 'controles_soporte';

    protected $fillable = [
        'equipment_file_id',
        'record_date',
        'area',
        'support_number',
        'content_summary',
        'observations',
    ];

    public function equipmentFile(): BelongsTo
    {
        return $this->belongsTo(EquipmentFile::class);
    }
}
