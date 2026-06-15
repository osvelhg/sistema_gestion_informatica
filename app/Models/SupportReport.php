<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportReport extends Model
{
    protected $table = 'reportes_soporte';

    protected $fillable = [
        'ticket_number',
        'entity_id',
        'department_id',
        'equipment_file_id',
        'incident_type_id',
        'title',
        'description',
        'reported_by',
        'status',
        'priority',
        'created_by',
        'closed_at',
    ];

    protected $casts = [
        'closed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (SupportReport $report) {
            if (empty($report->ticket_number)) {
                $last = static::max('id') ?? 0;
                $report->ticket_number = 'TCK-' . str_pad($last + 1, 6, '0', STR_PAD_LEFT);
            }
        });
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function equipmentFile(): BelongsTo
    {
        return $this->belongsTo(EquipmentFile::class);
    }

    public function incidentType(): BelongsTo
    {
        return $this->belongsTo(IncidentType::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
