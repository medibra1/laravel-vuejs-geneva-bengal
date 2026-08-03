<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreOwnerRequest;
use App\Http\Requests\Admin\UpdateOwnerRequest;
use App\Models\Owner;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class OwnerController extends Controller
{
    public function index(): Response
    {
        $owners = Owner::query()->latest()->paginate(20)->withQueryString();

        return Inertia::render('Admin/Owners/Index', [
            'owners' => $owners,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Owners/Form');
    }

    public function store(StoreOwnerRequest $request): RedirectResponse
    {
        Owner::create($request->validated());

        return redirect()->route('admin.owners.index')->with('success', __('Owner created.'));
    }

    public function edit(Owner $owner): Response
    {
        return Inertia::render('Admin/Owners/Form', [
            'owner' => $owner,
        ]);
    }

    public function update(UpdateOwnerRequest $request, Owner $owner): RedirectResponse
    {
        $owner->update($request->validated());

        return redirect()->route('admin.owners.index')->with('success', __('Owner updated.'));
    }

    public function destroy(Owner $owner): RedirectResponse
    {
        $owner->delete();

        return redirect()->route('admin.owners.index')->with('success', __('Owner deleted.'));
    }
}
