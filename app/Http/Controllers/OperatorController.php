<?php

namespace App\Http\Controllers;

use App\Models\Loket;
use App\Models\Ticket;
use App\Services\QueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperatorController extends Controller
{
    /**
     * Show the operator console with the list of active lokets.
     */
    public function index(): Response
    {
        return Inertia::render('Operator', [
            'lokets' => Loket::active()
                ->with('service:id,name,color')
                ->orderBy('sort_order')
                ->get(['id', 'service_id', 'name', 'officer_name']),
        ]);
    }

    /**
     * Return the current call and waiting queue for a loket, as JSON, for polling.
     */
    public function state(Request $request): JsonResponse
    {
        $loket = Loket::find($request->query('loket_id'));

        if ($loket === null) {
            return response()->json([
                'current' => null,
                'waiting' => [],
                'waiting_count' => 0,
            ]);
        }

        $current = Ticket::query()
            ->where('loket_id', $loket->id)
            ->whereIn('status', [Ticket::STATUS_CALLED, Ticket::STATUS_SERVING])
            ->forToday()
            ->latest('called_at')
            ->first(['id', 'code', 'status', 'called_at']);

        $waiting = Ticket::query()
            ->where('service_id', $loket->service_id)
            ->waiting()
            ->forToday()
            ->orderBy('number')
            ->get(['id', 'code', 'created_at']);

        return response()->json([
            'current' => $current,
            'waiting' => $waiting->take(10)->values(),
            'waiting_count' => $waiting->count(),
        ]);
    }

    /**
     * Call the next waiting ticket to the given loket.
     */
    public function callNext(Loket $loket): RedirectResponse
    {
        $ticket = app(QueueService::class)->callNext($loket);

        if ($ticket === null) {
            Inertia::flash('toast', ['type' => 'info', 'message' => 'Tidak ada antrian menunggu']);
        } else {
            Inertia::flash('toast', ['type' => 'success', 'message' => "Memanggil nomor {$ticket->code}"]);
        }

        return back();
    }

    /**
     * Re-announce the current ticket.
     */
    public function recall(Ticket $ticket): RedirectResponse
    {
        $ticket = app(QueueService::class)->recall($ticket);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Memanggil ulang nomor {$ticket->code}"]);

        return back();
    }

    /**
     * Skip the current ticket.
     */
    public function skip(Ticket $ticket): RedirectResponse
    {
        $ticket = app(QueueService::class)->skip($ticket);

        Inertia::flash('toast', ['type' => 'info', 'message' => "Melewati nomor {$ticket->code}"]);

        return back();
    }

    /**
     * Finish the current ticket.
     */
    public function finish(Ticket $ticket): RedirectResponse
    {
        $ticket = app(QueueService::class)->finish($ticket);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Selesai melayani nomor {$ticket->code}"]);

        return back();
    }
}
