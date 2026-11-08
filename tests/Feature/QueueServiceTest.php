<?php

use App\Models\Loket;
use App\Models\Service;
use App\Models\Ticket;
use App\Services\QueueService;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->queue = new QueueService;
});

test('issuing tickets for a service produces sequential codes', function () {
    $service = Service::factory()->create(['prefix' => 'A']);

    $first = $this->queue->issue($service);
    $second = $this->queue->issue($service);

    expect($first->code)->toBe('A-001');
    expect($first->number)->toBe(1);
    expect($first->status)->toBe(Ticket::STATUS_WAITING);
    expect($second->code)->toBe('A-002');
    expect($second->number)->toBe(2);
});

test('numbering is independent per service', function () {
    $ktp = Service::factory()->create(['prefix' => 'A']);
    $kk = Service::factory()->create(['prefix' => 'B']);

    $ktpTicket = $this->queue->issue($ktp);
    $kkTicket = $this->queue->issue($kk);

    expect($ktpTicket->code)->toBe('A-001');
    expect($kkTicket->code)->toBe('B-001');
});

test('callNext returns the oldest waiting ticket and marks it called with loket id', function () {
    $service = Service::factory()->create(['prefix' => 'A']);
    $loket = Loket::factory()->for($service)->create();

    $first = $this->queue->issue($service);
    $this->queue->issue($service);

    $called = $this->queue->callNext($loket);

    expect($called)->not->toBeNull();
    expect($called->id)->toBe($first->id);
    expect($called->status)->toBe(Ticket::STATUS_CALLED);
    expect($called->loket_id)->toBe($loket->id);
    expect($called->called_at)->not->toBeNull();
});

test('a second callNext auto-finishes the previous ticket and returns the next', function () {
    $service = Service::factory()->create(['prefix' => 'A']);
    $loket = Loket::factory()->for($service)->create();

    $first = $this->queue->issue($service);
    $second = $this->queue->issue($service);

    $firstCalled = $this->queue->callNext($loket);
    $secondCalled = $this->queue->callNext($loket);

    expect($secondCalled->id)->toBe($second->id);
    expect($secondCalled->status)->toBe(Ticket::STATUS_CALLED);

    expect($firstCalled->fresh()->status)->toBe(Ticket::STATUS_DONE);
    expect($firstCalled->fresh()->finished_at)->not->toBeNull();
});

test('callNext returns null when there are no waiting tickets', function () {
    $service = Service::factory()->create(['prefix' => 'A']);
    $loket = Loket::factory()->for($service)->create();

    expect($this->queue->callNext($loket))->toBeNull();
});

test('skip sets the ticket status to skipped', function () {
    $service = Service::factory()->create(['prefix' => 'A']);
    $ticket = $this->queue->issue($service);

    $skipped = $this->queue->skip($ticket);

    expect($skipped->status)->toBe(Ticket::STATUS_SKIPPED);
});

test('finish sets the ticket status to done with finished_at', function () {
    $service = Service::factory()->create(['prefix' => 'A']);
    $ticket = $this->queue->issue($service);

    $finished = $this->queue->finish($ticket);

    expect($finished->status)->toBe(Ticket::STATUS_DONE);
    expect($finished->finished_at)->not->toBeNull();
});

test('a ticket issued before 07:00 WIB is still callable after 07:00 the same local day', function () {
    $service = Service::factory()->create(['prefix' => 'A']);
    $loket = Loket::factory()->for($service)->create();

    // 06:50 WIB is 23:50 UTC the previous day; 07:10 WIB is 00:10 UTC.
    $this->travelTo(CarbonImmutable::parse('2026-11-09 06:50', 'Asia/Jakarta'));
    $ticket = $this->queue->issue($service);

    $this->travelTo(CarbonImmutable::parse('2026-11-09 07:10', 'Asia/Jakarta'));
    $called = $this->queue->callNext($loket);

    expect($ticket->issued_date->toDateString())->toBe('2026-11-09');
    expect($called?->id)->toBe($ticket->id);
});
