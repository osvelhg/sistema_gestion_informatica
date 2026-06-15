<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityIncidentRecord extends Model
{
    protected $table = 'incidentes_seguridad';

    protected $fillable = [
        'equipment_file_id',
        'incident_date',
        'incident_time',
        'area',
        'consecutive_number',
        'detected_fact',
        'detection_method',
        'measures_taken',
        'observations',
    ];

    public function equipmentFile(): BelongsTo
    {
        return $this->belongsTo(EquipmentFile::class);
    }
}
