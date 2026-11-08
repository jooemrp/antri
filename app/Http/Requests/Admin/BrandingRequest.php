<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BrandingRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'app_name' => ['required', 'string', 'max:255'],
            'primary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'accent_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'running_texts' => ['nullable', 'array'],
            'running_texts.*' => ['array'],
            // Blank rows arrive as null: the global ConvertEmptyStringsToNull
            // middleware rewrites '' before validation. Branding::clean() drops them.
            'running_texts.*.text' => ['nullable', 'string', 'max:500'],
            // Null means automatic: the display uses its theme's text colour.
            'running_texts.*.color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'display_theme' => ['required', 'in:dark,light'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ];
    }
}
