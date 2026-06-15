<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentMovement extends Model
{
    protected $table = 'movimientos_equipos';

    protected $fillable = [
        'equipment_file_id', 'inventory_number',
        'from_entity_id', 'from_department_id',
        'to_entity_id', 'to_department_id',
        'moved_by', 'moved_at',
    ];

    protected $casts = ['moved_at' => 'datetime'];

    public function equipmentFile(): BelongsTo
    {
        return $this->belongsTo(EquipmentFile::class);
    }

    public function fromEntity(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'from_entity_id');
    }

    public function fromDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'from_department_id');
    }

    public function toEntity(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'to_entity_id');
    }

    public function toDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'to_department_id');
    }

    public function movedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moved_by');
    }
}
