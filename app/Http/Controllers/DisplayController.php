<?php

namespace App\Http\Controllers;

use App\Models\Loket;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class DisplayController extends Controller
{
    /**
     * Show the public display/caller screen with the list of active lokets.
     */
    public function index(): Response
    {
        return Inertia::render('Display', [
            'lokets' => Loket::active()
                ->with('service')
                ->orderBy('sort_order')
                ->get(['id', 'name', 'service_id']),
        ]);
    }

    /**
     * Return the current call for each active loket, plus the most recently
     * called ticket overall, as JSON, for polling.
     */
    public function state(): JsonResponse
    {
        $lokets = Loket::active()
            ->with('service')
            ->orderBy('sort_order')
            ->get(['id', 'name', 'service_id']);

        $calls = $lokets->map(function (Loket $loket) {
            $ticket = Ticket::query()
                ->where('loket_id', $loket->id)
                ->whereIn('status', [Ticket::STATUS_CALLED, Ticket::STATUS_SERVING])
                ->forToday()
                ->latest('called_at')
                ->first(['code', 'called_at']);

            return [
                'loket_id' => $loket->id,
                'loket_name' => $loket->name,
                'service_name' => $loket->service?->name,
                'service_color' => $loket->service?->color,
                'code' => $ticket?->code,
                'called_at' => $ticket?->called_at?->toIso8601String(),
            ];
        })->values();

        $latestCall = $calls->filter(fn (array $call) => $call['called_at'] !== null)
            ->sortByDesc('called_at')
            ->first();

        return response()->json([
            'calls' => $calls,
            'latest' => $latestCall ? [
                'code' => $latestCall['code'],
                'loket_name' => $latestCall['loket_name'],
                'called_at' => $latestCall['called_at'],
            ] : null,
        ]);
    }
}
