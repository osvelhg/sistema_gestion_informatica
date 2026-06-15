<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InspectionRecord extends Model
{
    protected $table = 'registros_inspeccion';

    protected $fillable = [
        'equipment_file_id',
        'entity_id',
        'department_id',
        'inspection_date',
        'participants',
        'situations_detected',
        'worksheet_reference',
    ];

    public function equipmentFile(): BelongsTo
    {
        return $this->belongsTo(EquipmentFile::class);
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function workSheets(): HasMany
    {
        return $this->hasMany(WorkSheetRecord::class);
    }
}
