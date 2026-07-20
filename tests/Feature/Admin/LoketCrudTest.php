<?php

use App\Models\Loket;
use App\Models\Service;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('lokets index shows the lokets page', function () {
    $response = $this->get(route('admin.lokets.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('admin/Lokets'));
});

test('a loket can be created', function () {
    $service = Service::factory()->create();

    $response = $this->post(route('admin.lokets.store'), [
        'service_id' => $service->id,
        'name' => 'Loket 1',
        'officer_name' => 'Budi',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('lokets', [
        'service_id' => $service->id,
        'name' => 'Loket 1',
        'officer_name' => 'Budi',
    ]);
});

test('a loket can be updated', function () {
    $service = Service::factory()->create();
    $loket = Loket::factory()->for($service)->create(['name' => 'Loket 1']);

    $response = $this->patch(route('admin.lokets.update', $loket), [
        'service_id' => $service->id,
        'name' => 'Loket 2',
        'officer_name' => 'Siti',
        'is_active' => false,
        'sort_order' => 3,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('lokets', [
        'id' => $loket->id,
        'name' => 'Loket 2',
        'officer_name' => 'Siti',
        'is_active' => false,
    ]);
});

test('a loket can be deleted', function () {
    $loket = Loket::factory()->create();

    $response = $this->delete(route('admin.lokets.destroy', $loket));

    $response->assertRedirect();
    $this->assertDatabaseMissing('lokets', ['id' => $loket->id]);
});
