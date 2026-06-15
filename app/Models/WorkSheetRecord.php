<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkSheetRecord extends Model
{
    protected $table = 'hojas_trabajo';

    protected $fillable = [
        'equipment_file_id',
        'inspection_record_id',
        'work_date',
        'worksheet_number',
        'control_area',
        'controlled_area',
        'control_action_type',
        'started_at',
        'ended_at',
        'preliminary_results',
        'observations',
        'controller_name',
        'controlled_name',
        'checklist',
    ];

    protected $casts = ['checklist' => 'array'];

    public function equipmentFile(): BelongsTo
    {
        return $this->belongsTo(EquipmentFile::class);
    }

    public function inspectionRecord(): BelongsTo
    {
        return $this->belongsTo(InspectionRecord::class);
    }
}
