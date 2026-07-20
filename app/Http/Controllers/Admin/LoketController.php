<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LoketRequest;
use App\Models\Loket;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class LoketController extends Controller
{
    /**
     * Show the list of lokets and the services available to assign them to.
     */
    public function index(): Response
    {
        return Inertia::render('admin/Lokets', [
            'lokets' => Loket::query()
                ->with('service:id,name,color')
                ->orderBy('sort_order')
                ->get(),
            'services' => Service::query()->orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    /**
     * Create a new loket.
     */
    public function store(LoketRequest $request): RedirectResponse
    {
        Loket::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Loket berhasil ditambahkan.')]);

        return back();
    }

    /**
     * Update the given loket.
     */
    public function update(LoketRequest $request, Loket $loket): RedirectResponse
    {
        $loket->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Loket berhasil diperbarui.')]);

        return back();
    }

    /**
     * Delete the given loket.
     */
    public function destroy(Loket $loket): RedirectResponse
    {
        $loket->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Loket berhasil dihapus.')]);

        return back();
    }
}
