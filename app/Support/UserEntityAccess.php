<?php

namespace App\Support;

use App\Models\Entity;
use App\Models\Municipio;
use App\Models\Provincia;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Alcance de datos por usuario: provincia AD (directorio) o entidades explícitas.
 */
class UserEntityAccess
{
    public const MODE_PROVINCE_DIRECTORY = 'province_directory';

    public const MODE_RESTRICTED_ENTITIES = 'restricted_entities';

    public static function bypasses(?User $user): bool
    {
        if (!$user) {
            return true;
        }

        return $user->hasRole('Administrador');
    }

    /**
     * IDs de entidades visibles; null = sin filtro (toda la tabla).
     *
     * @return array<int>|null
     */
    public static function allowedEntityIds(?User $user): ?array
    {
        if (self::bypasses($user)) {
            return null;
        }

        $mode = $user->entity_access_mode ?? self::MODE_RESTRICTED_ENTITIES;

        if ($mode === self::MODE_PROVINCE_DIRECTORY) {
            $sigla = $user->ad_provincia_sigla;
            if (!$sigla) {
                return [];
            }

            return Entity::query()
                ->whereHas('municipio.provincia', function (Builder $q) use ($sigla) {
                    $s = strtoupper(trim($sigla));
                    $q->where(function (Builder $q2) use ($s) {
                        $q2->whereRaw('upper(trim(coalesce(sigla_2, \'\'))) = ?', [$s])
                            ->orWhereRaw('upper(trim(coalesce(sigla_3, \'\'))) = ?', [$s])
                            ->orWhereRaw('upper(trim(coalesce(code, \'\'))) = ?', [$s]);
                    });
                })
                ->pluck('id')
                ->all();
        }

        return $user->entities()->pluck('entidades.id')->all();
    }

    /**
     * Filtra por columna entity_id (p. ej. pisos_venta.entity_id).
     */
    public static function whereEntityIdAllowed(Builder $query, ?User $user, string $column = 'entity_id'): void
    {
        $ids = self::allowedEntityIds($user);
        if ($ids === null) {
            return;
        }
        if ($ids === []) {
            $query->whereRaw('1 = 0');

            return;
        }
        $query->whereIn($column, $ids);
    }

    /**
     * Modelo Entidad: whereIn id.
     */
    public static function applyToEntitiesQuery(Builder $query, ?User $user): void
    {
        $ids = self::allowedEntityIds($user);
        if ($ids === null) {
            return;
        }
        if ($ids === []) {
            $query->whereRaw('1 = 0');

            return;
        }
        $table = $query->getModel()->getTable();
        $query->whereIn($table.'.id', $ids);
    }

    /**
     * IDs de provincias visibles para nomencladores; null = todas.
     *
     * @return array<int>|null
     */
    public static function allowedProvinciaIds(?User $user): ?array
    {
        if (self::bypasses($user)) {
            return null;
        }

        $mode = $user->entity_access_mode ?? self::MODE_RESTRICTED_ENTITIES;

        if ($mode === self::MODE_PROVINCE_DIRECTORY && $user->ad_provincia_sigla) {
            $s = strtoupper(trim($user->ad_provincia_sigla));

            return Provincia::query()
                ->where(function (Builder $q) use ($s) {
                    $q->whereRaw('upper(trim(coalesce(sigla_2, \'\'))) = ?', [$s])
                        ->orWhereRaw('upper(trim(coalesce(sigla_3, \'\'))) = ?', [$s])
                        ->orWhereRaw('upper(trim(coalesce(code, \'\'))) = ?', [$s]);
                })
                ->pluck('id')
                ->all();
        }

        $entityIds = $user->entities()->pluck('entidades.id');
        if ($entityIds->isEmpty()) {
            return [];
        }

        $municipioIds = Entity::query()->whereIn('id', $entityIds)->pluck('municipio_id')->filter()->unique();

        return Provincia::query()
            ->whereHas('municipios', fn (Builder $q) => $q->whereIn('id', $municipioIds))
            ->pluck('id')
            ->all();
    }

    /**
     * IDs de municipios visibles; null = todos.
     *
     * @return array<int>|null
     */
    public static function allowedMunicipioIds(?User $user): ?array
    {
        $provIds = self::allowedProvinciaIds($user);
        if ($provIds === null) {
            return null;
        }
        if ($provIds === []) {
            return [];
        }

        return Municipio::query()->whereIn('provincia_id', $provIds)->pluck('id')->all();
    }
}
