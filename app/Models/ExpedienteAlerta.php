<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpedienteAlerta extends Model
{
    public const TYPE_RODAS_INVENTARIO_INEXISTENTE = 'rodas_inventario_inexistente';

    public const TYPE_RODAS_INCONGRUENCIA = 'rodas_incongruencia';

    /** Medios básicos: periféricos y otros dispositivos con inventario */
    public const TYPE_RODAS_MEDIO_INVENTARIO_INEXISTENTE = 'rodas_medio_inventario_inexistente';

    public const TYPE_RODAS_MEDIO_INCONGRUENCIA = 'rodas_medio_incongruencia';

    /** Responsable indicado a mano (no verificado en Active Directory). */
    public const TYPE_RESPONSIBLE_MANUAL = 'responsible_sin_ad';

    protected $table = 'expediente_alertas';

    protected $fillable = [
        'equipment_file_id',
        'type',
        'message',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function equipmentFile(): BelongsTo
    {
        return $this->belongsTo(EquipmentFile::class, 'equipment_file_id');
    }
}
