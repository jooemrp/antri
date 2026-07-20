<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Services\QueueService;
use Inertia\Inertia;
use Inertia\Response;

class KioskController extends Controller
{
    /**
     * Show the kiosk screen with the active service lines.
     */
    public function index(): Response
    {
        return Inertia::render('Kiosk', [
            'services' => Service::active()->orderBy('sort_order')->get(['id', 'name', 'prefix', 'color']),
        ]);
    }

    /**
     * Issue a new queue ticket for the given service and show it on the kiosk.
     */
    public function store(Service $service): Response
    {
        $ticket = app(QueueService::class)->issue($service);

        return Inertia::render('Kiosk', [
            'services' => Service::active()->orderBy('sort_order')->get(['id', 'name', 'prefix', 'color']),
            'issuedTicket' => [
                'code' => $ticket->code,
                'service_name' => $service->name,
                'issued_at' => $ticket->created_at->toIso8601String(),
            ],
        ]);
    }
}
