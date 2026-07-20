<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

class Branding
{
    /**
     * Resolve the branding array from settings, applying defaults.
     *
     * @return array<string, string|null>
     */
    public static function resolve(): array
    {
        $logoPath = Setting::get('logo_path');

        return [
            'app_name' => Setting::get('app_name', config('app.name')),
            'logo_path' => $logoPath,
            'logo_url' => $logoPath ? Storage::disk('public')->url($logoPath) : null,
            'primary_color' => Setting::get('primary_color', '#2563eb'),
            'accent_color' => Setting::get('accent_color', '#16a34a'),
            'running_text' => Setting::get('running_text', 'Selamat datang di Dinas Kependudukan dan Pencatatan Sipil'),
        ];
    }
}
