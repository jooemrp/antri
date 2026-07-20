<?php

namespace App\Services;

use App\Models\Loket;
use App\Models\Service;
use App\Models\Ticket;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class QueueService
{
    /**
     * Issue the next queue ticket for a service (daily sequence, per service).
     *
     * Safe under concurrent calls: the next number is computed under a row lock
     * inside a transaction, and the unique (service_id, issued_date, number)
     * constraint is the final guard. On a unique collision we retry once.
     */
    public function issue(Service $service): Ticket
    {
        try {
            return $this->issueOnce($service);
        } catch (QueryException $e) {
            return $this->issueOnce($service);
        }
    }

    protected function issueOnce(Service $service): Ticket
    {
        return DB::transaction(function () use ($service): Ticket {
            $lastNumber = Ticket::query()
                ->where('service_id', $service->id)
                ->whereDate('issued_date', today())
                ->lockForUpdate()
                ->max('number');

            $number = (int) $lastNumber + 1;

            return Ticket::create([
                'service_id' => $service->id,
                'loket_id' => null,
                'number' => $number,
                'code' => sprintf('%s-%03d', $service->prefix, $number),
                'status' => Ticket::STATUS_WAITING,
                'issued_date' => today(),
            ]);
        });
    }

    /**
     * Call the oldest waiting ticket for the loket's service to this loket.
     *
     * Auto-finishes any ticket currently called/serving at this loket before
     * advancing. Returns null when there is nothing left to call.
     */
    public function callNext(Loket $loket): ?Ticket
    {
        return DB::transaction(function () use ($loket): ?Ticket {
            $ticket = Ticket::query()
                ->where('service_id', $loket->service_id)
                ->whereDate('issued_date', today())
                ->where('status', Ticket::STATUS_WAITING)
                ->orderBy('number')
                ->lockForUpdate()
                ->first();

            if ($ticket === null) {
                return null;
            }

            Ticket::query()
                ->where('loket_id', $loket->id)
                ->whereIn('status', [Ticket::STATUS_CALLED, Ticket::STATUS_SERVING])
                ->update([
                    'status' => Ticket::STATUS_DONE,
                    'finished_at' => now(),
                ]);

            $ticket->update([
                'status' => Ticket::STATUS_CALLED,
                'loket_id' => $loket->id,
                'called_at' => now(),
            ]);

            return $ticket;
        });
    }

    /**
     * Re-announce a ticket by bumping its called_at (status unchanged).
     */
    public function recall(Ticket $ticket): Ticket
    {
        $ticket->update(['called_at' => now()]);

        return $ticket;
    }

    /**
     * Skip a ticket.
     */
    public function skip(Ticket $ticket): Ticket
    {
        $ticket->update(['status' => Ticket::STATUS_SKIPPED]);

        return $ticket;
    }

    /**
     * Mark a ticket as done.
     */
    public function finish(Ticket $ticket): Ticket
    {
        $ticket->update([
            'status' => Ticket::STATUS_DONE,
            'finished_at' => now(),
        ]);

        return $ticket;
    }

    /**
     * Mark a ticket as being served.
     */
    public function startServing(Ticket $ticket): Ticket
    {
        $ticket->update([
            'status' => Ticket::STATUS_SERVING,
            'served_at' => now(),
        ]);

        return $ticket;
    }
}
