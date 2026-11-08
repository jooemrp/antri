<?php

use App\Models\Loket;
use App\Models\Service;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('services index shows the services page', function () {
    $response = $this->get(route('admin.services.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('admin/Services'));
});

test('services index includes loket and ticket counts for the delete warning', function () {
    $service = Service::factory()->create();
    Loket::factory()->count(2)->create(['service_id' => $service->id]);

    $response = $this->get(route('admin.services.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('services.0.lokets_count', 2)
        ->where('services.0.tickets_count', 0));
});

test('a service can be created', function () {
    $response = $this->post(route('admin.services.store'), [
        'name' => 'KTP',
        'prefix' => 'A',
        'color' => '#2563eb',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('services', [
        'name' => 'KTP',
        'prefix' => 'A',
    ]);
});

test('a service can be updated', function () {
    $service = Service::factory()->create(['name' => 'KTP', 'prefix' => 'A']);

    $response = $this->patch(route('admin.services.update', $service), [
        'name' => 'KTP Baru',
        'prefix' => 'B',
        'color' => '#16a34a',
        'is_active' => false,
        'sort_order' => 2,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('services', [
        'id' => $service->id,
        'name' => 'KTP Baru',
        'prefix' => 'B',
        'is_active' => false,
    ]);
});

test('a service can be deleted', function () {
    $service = Service::factory()->create();

    $response = $this->delete(route('admin.services.destroy', $service));

    $response->assertRedirect();
    $this->assertDatabaseMissing('services', ['id' => $service->id]);
});
