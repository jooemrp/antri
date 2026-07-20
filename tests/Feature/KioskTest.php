<?php

use App\Models\Service;
use App\Models\Ticket;
use Inertia\Testing\AssertableInertia as Assert;

test('kiosk index shows active services', function () {
    $service = Service::factory()->create(['is_active' => true]);
    Service::factory()->create(['is_active' => false]);

    $response = $this->get(route('kiosk'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Kiosk')
        ->has('services', 1)
        ->where('services.0.id', $service->id)
    );
});

test('issuing a ticket from the kiosk creates a waiting ticket and returns it', function () {
    $service = Service::factory()->create(['prefix' => 'A']);

    $response = $this->post(route('kiosk.issue', $service));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Kiosk')
        ->where('issuedTicket.code', 'A-001')
        ->where('issuedTicket.service_name', $service->name)
    );

    $this->assertDatabaseHas('tickets', [
        'service_id' => $service->id,
        'code' => 'A-001',
        'status' => Ticket::STATUS_WAITING,
    ]);
});

test('kiosk state returns the latest issued and called number per active service', function () {
    $service = Service::factory()->create(['is_active' => true, 'prefix' => 'A']);

    Ticket::factory()->create([
        'service_id' => $service->id,
        'number' => 9,
        'code' => 'A-009',
        'called_at' => now()->subMinutes(5),
    ]);
    Ticket::factory()->create([
        'service_id' => $service->id,
        'number' => 14,
        'code' => 'A-014',
        'called_at' => null,
    ]);

    $response = $this->get(route('kiosk.state'));

    $response->assertOk();
    $response->assertJsonPath('services.0.id', $service->id);
    $response->assertJsonPath('services.0.last_issued', 'A-014');
    $response->assertJsonPath('services.0.last_called', 'A-009');
});

test('kiosk state excludes inactive services', function () {
    Service::factory()->create(['is_active' => true]);
    Service::factory()->create(['is_active' => false]);

    $response = $this->get(route('kiosk.state'));

    $response->assertOk();
    $response->assertJsonCount(1, 'services');
});

test('kiosk state returns nulls when a service has no tickets', function () {
    Service::factory()->create(['is_active' => true]);

    $response = $this->get(route('kiosk.state'));

    $response->assertJsonPath('services.0.last_issued', null);
    $response->assertJsonPath('services.0.last_called', null);
});

test('kiosk state ignores tickets issued on a previous day', function () {
    $service = Service::factory()->create(['is_active' => true, 'prefix' => 'A']);

    Ticket::factory()->create([
        'service_id' => $service->id,
        'number' => 30,
        'code' => 'A-030',
        'issued_date' => today()->subDay(),
        'called_at' => now()->subDay(),
    ]);

    $response = $this->get(route('kiosk.state'));

    $response->assertJsonPath('services.0.last_issued', null);
    $response->assertJsonPath('services.0.last_called', null);
});
