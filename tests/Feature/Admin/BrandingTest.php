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
        'running_texts' => [
            ['text' => 'Teks satu'],
            ['text' => 'Teks dua', 'color' => '#facc15'],
        ],
    ]);

    $response->assertRedirect();
    expect(Setting::get('app_name'))->toBe('Disdukcapil Kota Baru');
    expect(Setting::get('primary_color'))->toBe('#123456');
    expect(Setting::get('accent_color'))->toBe('#654321');
    expect(Setting::get('display_theme'))->toBe('light');
    expect(Branding::resolve()['running_texts'])->toBe([
        ['text' => 'Teks satu', 'color' => null],
        ['text' => 'Teks dua', 'color' => '#facc15'],
    ]);
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
        'running_texts' => [
            ['text' => 'Teks satu'],
            ['text' => '   ', 'color' => '#facc15'],
            ['text' => ''],
        ],
    ]);

    expect(Branding::resolve()['running_texts'])->toBe([['text' => 'Teks satu', 'color' => null]]);
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

test('running texts decode from stored json with per-line colours', function () {
    Setting::set('running_text', json_encode([
        ['text' => 'Satu', 'color' => '#ff0000'],
        ['text' => 'Dua', 'color' => null],
    ]));

    expect(Branding::resolve()['running_texts'])->toBe([
        ['text' => 'Satu', 'color' => '#ff0000'],
        ['text' => 'Dua', 'color' => null],
    ]);
});

test('a legacy list of plain strings resolves with automatic colours', function () {
    Setting::set('running_text', json_encode(['Satu', 'Dua']));

    expect(Branding::resolve()['running_texts'])->toBe([
        ['text' => 'Satu', 'color' => null],
        ['text' => 'Dua', 'color' => null],
    ]);
});

test('a legacy plain-string running text resolves as a single entry', function () {
    Setting::set('running_text', 'Teks lama');

    expect(Branding::resolve()['running_texts'])->toBe([['text' => 'Teks lama', 'color' => null]]);
});

test('running texts fall back to the default when unset', function () {
    expect(Branding::resolve()['running_texts'])
        ->toBe([['text' => 'Selamat datang di Dinas Kependudukan dan Pencatatan Sipil', 'color' => null]]);
});

test('an empty stored array means no running text', function () {
    Setting::set('running_text', json_encode([]));

    expect(Branding::resolve()['running_texts'])->toBe([]);
});

test('the logo url is root-relative so it loads from any host the app is opened on', function () {
    config(['filesystems.disks.public.url' => 'http://antrian-app.test/storage']);
    Setting::set('logo_path', 'branding/logo.png');

    expect(Branding::resolve()['logo_url'])->toBe('/storage/branding/logo.png');
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
        'running_texts' => [['text' => 'Teks satu'], ['text' => str_repeat('a', 501)]],
    ]);

    $response->assertSessionHasErrors('running_texts.1.text');
    $response->assertSessionDoesntHaveErrors('running_texts.0.text');
});

test('an invalid running text line colour is rejected on that line', function () {
    $response = $this->post(route('admin.branding.update'), [
        'app_name' => 'Disdukcapil',
        'primary_color' => '#2563eb',
        'accent_color' => '#16a34a',
        'display_theme' => 'dark',
        'running_texts' => [['text' => 'Satu', 'color' => '#facc15'], ['text' => 'Dua', 'color' => 'yellow']],
    ]);

    $response->assertSessionHasErrors('running_texts.1.color');
    $response->assertSessionDoesntHaveErrors('running_texts.0.color');
});
