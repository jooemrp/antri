<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BrandingRequest;
use App\Models\Setting;
use App\Support\Branding;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BrandingController extends Controller
{
    /**
     * Show the branding settings form.
     */
    public function edit(): Response
    {
        return Inertia::render('admin/Branding', [
            'branding' => Branding::resolve(),
        ]);
    }

    /**
     * Update the branding settings.
     */
    public function update(BrandingRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        Setting::set('app_name', $validated['app_name']);
        Setting::set('primary_color', $validated['primary_color']);
        Setting::set('accent_color', $validated['accent_color']);
        Setting::set('running_text', $validated['running_text'] ?? null);

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('branding', 'public');

            Setting::set('logo_path', $path);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tampilan berhasil diperbarui.')]);

        return back();
    }
}
