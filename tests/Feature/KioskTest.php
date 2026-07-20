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
