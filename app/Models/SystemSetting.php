<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $table = 'ajustes_sistema';

    protected $fillable = [
        'logo_path',
        'organization_name',
        'system_name',
        'header_title',
        'footer_left',
        'footer_right',
    ];

    public static function branding(): array
    {
        $s = static::first();
        return [
            'organization_name' => $s?->organization_name ?: 'SGI — Sistema de Gestión Informático',
            'system_name'       => $s?->system_name       ?: 'Departamento de Informática',
            'header_title'      => $s?->header_title      ?: 'Gestión de Activos Informáticos',
            'footer_left'       => $s?->footer_left       ?: '',
            'footer_right'      => $s?->footer_right      ?: 'SGI',
        ];
    }

    public static function logoDataUrl(): ?string
    {
        $s = static::first();
        if (!$s?->logo_path) return null;
        $path = storage_path('app/public/' . $s->logo_path);
        if (!file_exists($path)) return null;
        $mime = mime_content_type($path) ?: 'image/png';
        return "data:{$mime};base64," . base64_encode(file_get_contents($path));
    }
}
