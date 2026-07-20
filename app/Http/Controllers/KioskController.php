<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\Ticket;
use App\Services\QueueService;
use Illuminate\Http\JsonResponse;
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

    /**
     * Return the latest issued and latest called number for each active
     * service today, as JSON, for polling from the kiosk screen.
     */
    public function state(): JsonResponse
    {
        $services = Service::active()->orderBy('sort_order')->get(['id', 'name', 'color']);

        return response()->json([
            'services' => $services->map(function (Service $service): array {
                $lastIssued = Ticket::query()
                    ->where('service_id', $service->id)
                    ->forToday()
                    ->latest('number')
                    ->first(['code']);

                $lastCalled = Ticket::query()
                    ->where('service_id', $service->id)
                    ->forToday()
                    ->whereNotNull('called_at')
                    ->latest('called_at')
                    ->first(['code']);

                return [
                    'id' => $service->id,
                    'name' => $service->name,
                    'color' => $service->color,
                    'last_issued' => $lastIssued?->code,
                    'last_called' => $lastCalled?->code,
                ];
            })->values(),
        ]);
    }
}
