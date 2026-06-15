<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemModule extends Model
{
    protected $table = 'modulos_sistema';

    protected $fillable = ['slug', 'name', 'enabled', 'description'];

    protected $casts = [
        'enabled' => 'boolean',
    ];
}
