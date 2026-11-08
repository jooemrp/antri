<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ServiceRequest;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    /**
     * Show the list of service lines.
     */
    public function index(): Response
    {
        return Inertia::render('admin/Services', [
            'services' => Service::query()->withCount(['lokets', 'tickets'])->orderBy('sort_order')->get(),
        ]);
    }

    /**
     * Create a new service line.
     */
    public function store(ServiceRequest $request): RedirectResponse
    {
        Service::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Layanan berhasil ditambahkan.')]);

        return back();
    }

    /**
     * Update the given service line.
     */
    public function update(ServiceRequest $request, Service $service): RedirectResponse
    {
        $service->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Layanan berhasil diperbarui.')]);

        return back();
    }

    /**
     * Delete the given service line.
     */
    public function destroy(Service $service): RedirectResponse
    {
        $service->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Layanan berhasil dihapus.')]);

        return back();
    }
}
