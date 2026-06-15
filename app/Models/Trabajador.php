<?php

namespace App\Models;

use App\Support\PersonNameNormalizer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Trabajador extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'trabajadores';

    protected $fillable = [
        'nombre',
        'ci',
        'telefono',
        'direccion',
        'municipio_id',
        'estado',
        'origen',
        'samaccountname',
        'cargo',
        'email',
    ];

    protected $casts = [
        'estado' => 'boolean',
    ];

    public function municipio()
    {
        return $this->belongsTo(Municipio::class);
    }

    public function sourcesAsignados()
    {
        return $this->belongsToMany(DatacellSource::class, 'fuente_trabajador', 'trabajador_id', 'source_id')
            ->withPivot('rolqr_id', 'fecha_alta', 'fecha_baja', 'estado')
            ->withTimestamps();
    }

    public function expedienteResponsables()
    {
        return $this->hasMany(EquipmentFileResponsible::class, 'trabajador_id');
    }

    public function scopeFromAd($query)
    {
        return $query->where('origen', 'active_directory');
    }

    public function scopeFromCi($query)
    {
        return $query->where('origen', 'ci_externo');
    }

    /**
     * Busca un trabajador existente por CI, usuario AD o nombre equivalente (normalizado).
     */
    public static function findExistingMatch(?string $ci, ?string $samaccountname, ?string $nombre = null): ?self
    {
        if ($ci && $ci !== '') {
            $found = static::withTrashed()->where('ci', $ci)->first();
            if ($found) {
                return $found;
            }
        }

        if ($samaccountname && $samaccountname !== '') {
            $found = static::withTrashed()->where('samaccountname', $samaccountname)->first();
            if ($found) {
                return $found;
            }
        }

        return static::findDuplicateByNormalizedName($nombre);
    }

    /**
     * Misma persona por nombre (mayúsculas, sin tildes ni signos), distinto ID.
     */
    public static function findDuplicateByNormalizedName(?string $nombre, ?int $exceptId = null): ?self
    {
        $fp = PersonNameNormalizer::fingerprint($nombre);
        if ($fp === '') {
            return null;
        }

        $q = static::withTrashed();
        if ($exceptId !== null) {
            $q->where('id', '!=', $exceptId);
        }

        foreach ($q->cursor() as $row) {
            if (PersonNameNormalizer::fingerprint($row->nombre) === $fp) {
                return $row;
            }
        }

        return null;
    }
}
