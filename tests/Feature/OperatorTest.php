<?php

use App\Models\Loket;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('operator index shows the operator console', function () {
    $response = $this->get(route('operator.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('Operator'));
});

test('calling next sets the oldest waiting ticket to called for that loket', function () {
    $service = Service::factory()->create(['prefix' => 'A']);
    $loket = Loket::factory()->for($service)->create();
    $ticket = Ticket::factory()->for($service)->create(['status' => Ticket::STATUS_WAITING]);

    $response = $this->post(route('operator.call', $loket));

    $response->assertRedirect();
    $this->assertDatabaseHas('tickets', [
        'id' => $ticket->id,
        'status' => Ticket::STATUS_CALLED,
        'loket_id' => $loket->id,
    ]);
});

test('state endpoint returns the current called ticket and waiting list', function () {
    $service = Service::factory()->create(['prefix' => 'A']);
    $loket = Loket::factory()->for($service)->create();
    $called = Ticket::factory()->for($service)->create([
        'status' => Ticket::STATUS_CALLED,
        'loket_id' => $loket->id,
        'called_at' => now(),
    ]);
    $waiting = Ticket::factory()->for($service)->create(['status' => Ticket::STATUS_WAITING]);

    $response = $this->getJson(route('operator.state', ['loket_id' => $loket->id]));

    $response->assertOk();
    $response->assertJsonPath('current.id', $called->id);
    $response->assertJsonPath('waiting_count', 1);
    $response->assertJsonPath('waiting.0.id', $waiting->id);
});

test('finishing a ticket sets its status to done', function () {
    $service = Service::factory()->create(['prefix' => 'A']);
    $loket = Loket::factory()->for($service)->create();
    $ticket = Ticket::factory()->for($service)->create([
        'status' => Ticket::STATUS_CALLED,
        'loket_id' => $loket->id,
    ]);

    $response = $this->post(route('operator.finish', $ticket));

    $response->assertRedirect();
    $this->assertDatabaseHas('tickets', [
        'id' => $ticket->id,
        'status' => Ticket::STATUS_DONE,
    ]);
});
