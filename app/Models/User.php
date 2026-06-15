<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    protected $table = 'usuarios';

    protected $fillable = [
        'name',
        'email',
        'password',
        'active',
        'ad_provincia_sigla',
        'entity_access_mode',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'active'            => 'boolean',
        ];
    }

    public function createdEquipmentFiles(): HasMany
    {
        return $this->hasMany(EquipmentFile::class, 'created_by');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function getRoleLabelAttribute(): string
    {
        return $this->roles->first()?->name ?? 'Sin rol';
    }

    public function entities(): BelongsToMany
    {
        return $this->belongsToMany(Entity::class, 'usuario_entidades')->withTimestamps();
    }
}
