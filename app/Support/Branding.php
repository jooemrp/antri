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
            // Path only: the disk URL is built from APP_URL, which breaks the
            // image when the app is opened on another host (IP, Expose, localhost).
            'logo_url' => $logoPath ? parse_url(Storage::disk('public')->url($logoPath), PHP_URL_PATH) : null,
            'primary_color' => Setting::get('primary_color', '#2563eb'),
            'accent_color' => Setting::get('accent_color', '#16a34a'),
            'running_texts' => self::runningTexts(),
            'display_theme' => Setting::get('display_theme', 'dark'),
        ];
    }

    /**
     * The running texts, decoded from the JSON array stored under `running_text`.
     *
     * Each line carries its own colour; a null colour means automatic (the
     * display's theme colour). Older installs stored a plain string, or a list
     * of plain strings; both resolve as lines with automatic colour.
     *
     * @return list<array{text: string, color: string|null}>
     */
    protected static function runningTexts(): array
    {
        $stored = Setting::get('running_text');

        if ($stored === null || $stored === '') {
            return [['text' => self::DEFAULT_RUNNING_TEXT, 'color' => null]];
        }

        $decoded = json_decode((string) $stored, true);

        if (! is_array($decoded)) {
            return [['text' => (string) $stored, 'color' => null]];
        }

        return self::clean($decoded);
    }

    /**
     * Normalise lines to {text, color}, trim text, and drop blank lines.
     *
     * Accepts legacy plain-string entries as well as {text, color} arrays; a
     * colour that isn't a 6-digit hex falls back to automatic (null).
     *
     * @param  array<int|string, mixed>  $lines
     * @return list<array{text: string, color: string|null}>
     */
    public static function clean(array $lines): array
    {
        $normalised = array_map(function (mixed $line): array {
            $text = is_array($line) ? ($line['text'] ?? '') : $line;
            $color = is_array($line) ? ($line['color'] ?? null) : null;

            return [
                'text' => trim((string) $text),
                'color' => is_string($color) && preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : null,
            ];
        }, $lines);

        return array_values(array_filter($normalised, fn (array $line): bool => $line['text'] !== ''));
    }
}
