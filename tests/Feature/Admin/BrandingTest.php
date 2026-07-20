<?php

use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('branding edit shows the branding page', function () {
    $response = $this->get(route('admin.branding.edit'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('admin/Branding'));
});

test('branding settings can be updated', function () {
    $response = $this->post(route('admin.branding.update'), [
        'app_name' => 'Disdukcapil Kota Baru',
        'primary_color' => '#123456',
        'accent_color' => '#654321',
        'running_text' => 'Selamat datang',
    ]);

    $response->assertRedirect();
    expect(Setting::get('app_name'))->toBe('Disdukcapil Kota Baru');
    expect(Setting::get('primary_color'))->toBe('#123456');
    expect(Setting::get('accent_color'))->toBe('#654321');
    expect(Setting::get('running_text'))->toBe('Selamat datang');
});

test('uploading a logo stores it on the public disk', function () {
    Storage::fake('public');

    $response = $this->post(route('admin.branding.update'), [
        'app_name' => 'Disdukcapil Kota Baru',
        'primary_color' => '#123456',
        'accent_color' => '#654321',
        'logo' => UploadedFile::fake()->image('logo.png'),
    ]);

    $response->assertRedirect();

    $logoPath = Setting::get('logo_path');

    expect($logoPath)->not->toBeNull();
    Storage::disk('public')->assertExists($logoPath);
});
