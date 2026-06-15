<?php

namespace App\Services;

use App\Models\EquipmentFile;
use App\Models\EquipmentMovement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MovementService
{
    public function __construct(private readonly AuditService $audit) {}

    public function move(EquipmentFile $file, int $toEntityId, int $toDepartmentId): EquipmentFile
    {
        return DB::transaction(function () use ($file, $toEntityId, $toDepartmentId) {
            $fromEntityId = $file->entity_id;
            $fromDepartmentId = $file->department_id;

            EquipmentMovement::create([
                'equipment_file_id'  => $file->id,
                'inventory_number'   => $file->inventory_number,
                'from_entity_id'     => $fromEntityId,
                'from_department_id' => $fromDepartmentId,
                'to_entity_id'       => $toEntityId,
                'to_department_id'   => $toDepartmentId,
                'moved_by'           => Auth::id(),
                'moved_at'           => now(),
            ]);

            $file->update([
                'entity_id'     => $toEntityId,
                'department_id' => $toDepartmentId,
            ]);

            $this->audit->log('MOVER', "Expediente {$file->file_number} movido de entidad {$fromEntityId} a {$toEntityId}", $file);

            return $file->fresh();
        });
    }
}
