<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EquipmentFile extends Model
{
    use SoftDeletes;

    protected $table = 'expedientes_equipos';

    protected $fillable = [
        'file_number', 'entity_id', 'department_id', 'type',
        'inventory_number', 'chassis', 'ip_address', 'station_name', 'operating_system',
        'status', 'repairable', 'responsible', 'seal_code', 'created_by',
    ];

    // -- Auto-generar file_number --
    protected static function booted(): void
    {
        static::creating(function (EquipmentFile $file) {
            if (empty($file->file_number)) {
                $last = static::withTrashed()->max('id') ?? 0;
                $file->file_number = 'EXP-' . str_pad($last + 1, 6, '0', STR_PAD_LEFT);
            }
        });
    }

    // -- Relaciones --

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function components(): HasMany
    {
        return $this->hasMany(Component::class);
    }

    public function seals(): HasMany
    {
        return $this->hasMany(Seal::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(EquipmentMovement::class);
    }

    public function inspectionRecords(): HasMany
    {
        return $this->hasMany(InspectionRecord::class);
    }

    public function workSheetRecords(): HasMany
    {
        return $this->hasMany(WorkSheetRecord::class);
    }

    public function securityIncidentRecords(): HasMany
    {
        return $this->hasMany(SecurityIncidentRecord::class);
    }

    public function supportControlRecords(): HasMany
    {
        return $this->hasMany(SupportControlRecord::class);
    }

    public function expedienteAlertas(): HasMany
    {
        return $this->hasMany(ExpedienteAlerta::class, 'equipment_file_id');
    }

    public function responsibles(): HasMany
    {
        return $this->hasMany(EquipmentFileResponsible::class, 'equipment_file_id')->orderBy('sort_order');
    }

    // -- Helpers para componentes por categoría --

    public function caracteristicas(): HasMany
    {
        return $this->components()->where('category', 'caracteristica');
    }

    public function perifericos(): HasMany
    {
        return $this->components()->where('category', 'periferico');
    }

    public function dispositivos(): HasMany
    {
        return $this->components()->where('category', 'dispositivo');
    }

    public function getComponentByType(string $type): ?Component
    {
        return $this->components->firstWhere('type', $type);
    }

    // -- Scopes --

    public function scopeSearch($query, ?string $search)
    {
        if (!$search) return $query;

        return $query->where(function ($q) use ($search) {
            $q->where('inventory_number', 'ilike', "%{$search}%")
              ->orWhere('file_number', 'ilike', "%{$search}%")
              ->orWhere('responsible', 'ilike', "%{$search}%");
        });
    }

    public function scopeFilterEntity($query, $entityId)
    {
        return $entityId ? $query->where('entity_id', $entityId) : $query;
    }

    public function scopeFilterDepartment($query, $departmentId)
    {
        return $departmentId ? $query->where('department_id', $departmentId) : $query;
    }

    public function scopeFilterStatus($query, $status)
    {
        return $status ? $query->where('status', $status) : $query;
    }
}
