<?php

namespace App\Support;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Storage;

class Branding
{
    private static ?SystemSetting $cachedRow = null;

    public static function forgetCache(): void
    {
        self::$cachedRow = null;
    }

    public static function settingsRow(): ?SystemSetting
    {
        return self::$cachedRow ??= SystemSetting::query()->first();
    }

    /**
     * @return array{organization_name: string, system_name: string, header_title: string, footer_left: string, footer_right: string}
     */
    public static function resolvedLayout(): array
    {
        $defaults = config('pdf_layout');
        $defaults = is_array($defaults) ? $defaults : [];
        $row = self::settingsRow();

        $merge = function (string $key) use ($row, $defaults): string {
            $fallback = (string) ($defaults[$key] ?? '');

            if ($row === null) {
                return $fallback;
            }

            $value = $row->{$key} ?? null;

            return $value !== null && trim((string) $value) !== '' ? (string) $value : $fallback;
        };

        return [
            'organization_name' => $merge('organization_name'),
            'system_name' => $merge('system_name'),
            'header_title' => $merge('header_title'),
            'footer_left' => $merge('footer_left'),
            'footer_right' => $merge('footer_right'),
        ];
    }

    public static function logoPublicUrl(): ?string
    {
        $row = self::settingsRow();
        if (! $row || ! $row->logo_path) {
            return null;
        }

        return Storage::disk('public')->url($row->logo_path);
    }

    public static function logoDiskPath(): ?string
    {
        $row = self::settingsRow();
        if (! $row || ! $row->logo_path) {
            return null;
        }

        $path = Storage::disk('public')->path($row->logo_path);

        return is_file($path) ? $path : null;
    }

    public static function logoDataUrlForPdf(): ?string
    {
        $path = self::logoDiskPath();
        if ($path === null) {
            return null;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'image/png',
        };

        $raw = @file_get_contents($path);
        if ($raw === false) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($raw);
    }
}
