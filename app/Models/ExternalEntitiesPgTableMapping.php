<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalEntitiesPgTableMapping extends Model
{
    protected $table = 'mapeos_tabla_entidades_externas';

    protected $fillable = [
        'external_entities_pg_setting_id',
        'sort_order',
        'enabled',
        'target',
        'schema_name',
        'table_name',
        'name_column',
        'code_column',
        'municipio_code_column',
        'provincia_code_column',
        'sigla_2_column',
        'sigla_3_column',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function setting(): BelongsTo
    {
        return $this->belongsTo(ExternalEntitiesPgSetting::class, 'external_entities_pg_setting_id');
    }
}
