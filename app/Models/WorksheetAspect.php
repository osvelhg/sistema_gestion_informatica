<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorksheetAspect extends Model
{
    protected $table = 'aspectos_hoja_trabajo';

    protected $fillable = ['section', 'label', 'order', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('section')->orderBy('order')->orderBy('id');
    }
}
