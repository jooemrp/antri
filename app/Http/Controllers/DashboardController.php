<?php

namespace App\Http\Controllers;

use App\Models\Loket;
use App\Models\Service;
use App\Models\Ticket;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the dashboard with today's queue statistics.
     */
    public function index(): Response
    {
        $todayTickets = Ticket::query()->forToday()->get(['id', 'service_id', 'status']);

        $perService = Service::query()
            ->orderBy('sort_order')
            ->get(['id', 'name', 'prefix', 'color'])
            ->map(function (Service $service) use ($todayTickets) {
                $serviceTickets = $todayTickets->where('service_id', $service->id);

                return [
                    'id' => $service->id,
                    'name' => $service->name,
                    'prefix' => $service->prefix,
                    'color' => $service->color,
                    'total' => $serviceTickets->count(),
                    'waiting' => $serviceTickets->where('status', Ticket::STATUS_WAITING)->count(),
                    'done' => $serviceTickets->where('status', Ticket::STATUS_DONE)->count(),
                ];
            });

        return Inertia::render('Dashboard', [
            'stats' => [
                'total_today' => $todayTickets->count(),
                'waiting' => $todayTickets->where('status', Ticket::STATUS_WAITING)->count(),
                'done' => $todayTickets->where('status', Ticket::STATUS_DONE)->count(),
                'active_lokets' => Loket::active()->count(),
                'per_service' => $perService->values(),
            ],
        ]);
    }
}
