<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

class Branding
{
    /**
     * The running text shown when nothing has been configured yet.
     */
    public const DEFAULT_RUNNING_TEXT = 'Selamat datang di Dinas Kependudukan dan Pencatatan Sipil';

    /**
     * Resolve the branding array from settings, applying defaults.
     *
     * @return array<string, mixed>
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
            'running_texts' => self::runningTexts(),
            'display_theme' => Setting::get('display_theme', 'dark'),
        ];
    }

    /**
     * The running texts, decoded from the JSON array stored under `running_text`.
     *
     * Installs created before multi-text support hold a plain string under this
     * key; that value is returned as a single-entry list.
     *
     * @return list<string>
     */
    protected static function runningTexts(): array
    {
        $stored = Setting::get('running_text');

        if ($stored === null || $stored === '') {
            return [self::DEFAULT_RUNNING_TEXT];
        }

        $decoded = json_decode((string) $stored, true);

        if (! is_array($decoded)) {
            return [(string) $stored];
        }

        return self::clean($decoded);
    }

    /**
     * Trim entries and drop the blank ones, reindexing the result.
     *
     * @param  array<int|string, mixed>  $texts
     * @return list<string>
     */
    public static function clean(array $texts): array
    {
        $trimmed = array_map(fn (mixed $text): string => trim((string) $text), $texts);

        return array_values(array_filter($trimmed, fn (string $text): bool => $text !== ''));
    }
}
