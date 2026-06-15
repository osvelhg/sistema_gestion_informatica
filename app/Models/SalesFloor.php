<?php

namespace App\Models;

use App\Support\UserEntityAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesFloor extends Model
{
    protected $table = 'pisos_venta';

    protected $fillable = [
        'municipio_code',
        'entity_id',
        'name',
        'address',
        'phone',
        'active',
        'network_type_id',
        'establishment_type_id',
        'establishment_status_id',
        'latitude',
        'longitude',
        'codigo_golden',
        'almacen_golden',
    ];

    protected $casts = [
        'active'           => 'boolean',
    ];

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'municipio_code', 'code');
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function networkType(): BelongsTo
    {
        return $this->belongsTo(NetworkType::class);
    }

    public function establishmentType(): BelongsTo
    {
        return $this->belongsTo(EstablishmentType::class);
    }

    public function establishmentStatus(): BelongsTo
    {
        return $this->belongsTo(EstablishmentStatus::class);
    }

    public function connectivityRecords(): HasMany
    {
        return $this->hasMany(ConnectivityRecord::class);
    }

    public function areasVenta(): HasMany
    {
        return $this->hasMany(AreaVenta::class);
    }

    /**
     * Fuentes QR vinculadas directamente al piso (sin áreas), p. ej. PV pequeño.
     *
     * @return BelongsToMany<DatacellSource, self>
     */
    public function pisoDatacellFuentes(): BelongsToMany
    {
        return $this->belongsToMany(DatacellSource::class, 'piso_venta_fuente', 'sales_floor_id', 'fuente_id')
            ->withPivot('id', 'canal_key', 'canal_electronico_id')
            ->withTimestamps();
    }

    public function cashRegisterModels(): BelongsToMany
    {
        return $this->belongsToMany(CashRegisterModel::class, 'piso_venta_modelo_caja')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Etiqueta de jerarquía geográfica / organizativa para mostrar en selects.
     */
    public function hierarchyLabel(): string
    {
        $parts = array_filter([
            $this->municipio?->name,
            $this->entity?->name,
            $this->name,
        ]);

        return implode(' · ', $parts);
    }

    /**
     * Etiqueta para módulos que muestran código y nombre de entidad sin municipio (p. ej. Conectividad).
     */
    public function hierarchyLabelEntityCode(): string
    {
        $codePart = $this->entity?->code ? '['.$this->entity->code.']' : null;
        $parts     = array_filter([
            $codePart,
            $this->entity?->name,
            $this->name,
        ]);

        return implode(' · ', $parts);
    }

    /**
     * Pisos para autocompletado (nombre, dirección, municipio, entidad, ID Datacell).
     *
     * @return \Illuminate\Support\Collection<int, array{id: int, name: string, datacell_piso_id: int|null, label: string}>
     */
    public static function searchForAutocomplete(?string $q, int $limit = 30, bool $labelWithEntityCode = false, ?User $forUser = null): \Illuminate\Support\Collection
    {
        $query = static::query()
            ->when($forUser, fn ($q2) => UserEntityAccess::whereEntityIdAllowed($q2, $forUser, 'entity_id'))
            ->with($labelWithEntityCode ? ['entity:id,name,code'] : ['municipio:id,name', 'entity:id,name'])
            ->orderBy('name');

        if ($q !== null && trim($q) !== '') {
            $term = trim($q);
            $query->where(function ($w) use ($term) {
                $w->where('name', 'ilike', "%{$term}%")
                    ->orWhere('datacell_piso_id', 'like', "%{$term}%")
                    ->orWhere('address', 'ilike', "%{$term}%")
                    ->orWhere('phone', 'ilike', "%{$term}%")
                    ->orWhereHas('municipio', fn ($mq) => $mq->where('name', 'ilike', "%{$term}%"))
                    ->orWhereHas('entity', function ($eq) use ($term) {
                        $eq->where('name', 'ilike', "%{$term}%")
                            ->orWhere('code', 'ilike', "%{$term}%");
                    });
            });
        }

        return $query->limit($limit)->get()->map(function (self $f) use ($labelWithEntityCode) {
            $base   = $labelWithEntityCode ? $f->hierarchyLabelEntityCode() : $f->hierarchyLabel();
            $suffix = $f->datacell_piso_id ? ' (ID Piso: '.$f->datacell_piso_id.')' : '';

            return [
                'id'               => $f->id,
                'name'             => $f->name,
                'datacell_piso_id' => $f->datacell_piso_id,
                'label'            => $base.$suffix,
            ];
        });
    }
}
