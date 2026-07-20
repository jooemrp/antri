<?php

use App\Models\Loket;
use App\Models\Service;
use App\Models\Ticket;
use Inertia\Testing\AssertableInertia as Assert;

test('display index shows the display screen', function () {
    $response = $this->get(route('display'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('Display'));
});

test('state endpoint returns the current called ticket and a non-null latest', function () {
    $service = Service::factory()->create(['prefix' => 'A']);
    $loket = Loket::factory()->for($service)->create();
    $called = Ticket::factory()->for($service)->create([
        'status' => Ticket::STATUS_CALLED,
        'loket_id' => $loket->id,
        'called_at' => now(),
    ]);

    $response = $this->getJson(route('display.state'));

    $response->assertOk();
    $response->assertJsonPath('calls.0.loket_id', $loket->id);
    $response->assertJsonPath('calls.0.code', $called->code);
    $response->assertJsonPath('latest.code', $called->code);
    $response->assertJsonPath('latest.loket_name', $loket->name);
});
