<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Seal extends Model
{
    protected $table = 'precintos';

    protected $fillable = [
        'entity_id', 'equipment_file_id', 'incident_type_id', 'inventory_number',
        'code', 'removed_seal', 'applied_seal', 'reason', 'date', 'time', 'performed_by',
    ];

    protected $casts = ['date' => 'date'];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function equipmentFile(): BelongsTo
    {
        return $this->belongsTo(EquipmentFile::class);
    }

    public function incidentType(): BelongsTo
    {
        return $this->belongsTo(IncidentType::class);
    }
}
