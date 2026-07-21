<?php

use App\Models\Setting;
use App\Models\User;
use App\Support\Branding;
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
        'display_theme' => 'light',
        'running_texts' => ['Teks satu', 'Teks dua'],
    ]);

    $response->assertRedirect();
    expect(Setting::get('app_name'))->toBe('Disdukcapil Kota Baru');
    expect(Setting::get('primary_color'))->toBe('#123456');
    expect(Setting::get('accent_color'))->toBe('#654321');
    expect(Setting::get('display_theme'))->toBe('light');
    expect(Branding::resolve()['running_texts'])->toBe(['Teks satu', 'Teks dua']);
});

test('uploading a logo stores it on the public disk', function () {
    Storage::fake('public');

    $response = $this->post(route('admin.branding.update'), [
        'app_name' => 'Disdukcapil Kota Baru',
        'primary_color' => '#123456',
        'accent_color' => '#654321',
        'display_theme' => 'dark',
        'logo' => UploadedFile::fake()->image('logo.png'),
    ]);

    $response->assertRedirect();

    $logoPath = Setting::get('logo_path');

    expect($logoPath)->not->toBeNull();
    Storage::disk('public')->assertExists($logoPath);
});

test('blank running texts are dropped before saving', function () {
    $this->post(route('admin.branding.update'), [
        'app_name' => 'Disdukcapil',
        'primary_color' => '#123456',
        'accent_color' => '#654321',
        'display_theme' => 'dark',
        'running_texts' => ['Teks satu', '   ', ''],
    ]);

    expect(Branding::resolve()['running_texts'])->toBe(['Teks satu']);
});

test('an invalid display theme is rejected', function () {
    $response = $this->post(route('admin.branding.update'), [
        'app_name' => 'Disdukcapil',
        'primary_color' => '#123456',
        'accent_color' => '#654321',
        'display_theme' => 'neon',
    ]);

    $response->assertSessionHasErrors('display_theme');
});

test('running texts decode from stored json', function () {
    Setting::set('running_text', json_encode(['Satu', 'Dua']));

    expect(Branding::resolve()['running_texts'])->toBe(['Satu', 'Dua']);
});

test('a legacy plain-string running text resolves as a single entry', function () {
    Setting::set('running_text', 'Teks lama');

    expect(Branding::resolve()['running_texts'])->toBe(['Teks lama']);
});

test('running texts fall back to the default when unset', function () {
    expect(Branding::resolve()['running_texts'])
        ->toBe(['Selamat datang di Dinas Kependudukan dan Pencatatan Sipil']);
});

test('an empty stored array means no running text', function () {
    Setting::set('running_text', json_encode([]));

    expect(Branding::resolve()['running_texts'])->toBe([]);
});

test('display theme defaults to dark', function () {
    expect(Branding::resolve()['display_theme'])->toBe('dark');
});

test('a running text entry longer than 500 characters fails validation on its indexed key', function () {
    $response = $this->post(route('admin.branding.update'), [
        'app_name' => 'Disdukcapil',
        'primary_color' => '#123456',
        'accent_color' => '#654321',
        'display_theme' => 'dark',
        'running_texts' => ['Teks satu', str_repeat('a', 501)],
    ]);

    $response->assertSessionHasErrors('running_texts.1');
    $response->assertSessionDoesntHaveErrors('running_texts.0');
});
