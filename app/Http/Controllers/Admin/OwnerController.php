<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CatStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreOwnerRequest;
use App\Http\Requests\Admin\UpdateOwnerRequest;
use App\Models\Cat;
use App\Models\Color;
use App\Models\Owner;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class OwnerController extends Controller
{
    public function index(): Response
    {
        $owners = Owner::query()
            ->with(['desiredCat:id,name', 'desiredColor:id,name'])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Owners/Index', [
            'owners' => $owners,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Owners/Form', [
            'cats' => $this->adoptableCatOptions(),
            'colors' => Color::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreOwnerRequest $request): RedirectResponse
    {
        Owner::create($request->validated());

        return redirect()->route('admin.owners.index')->with('success', __('Owner created.'));
    }

    public function edit(Owner $owner): Response
    {
        return Inertia::render('Admin/Owners/Form', [
            'owner' => $owner->load(['desiredCat:id,name', 'desiredColor:id,name']),
            'cats' => $this->adoptableCatOptions(),
            'colors' => Color::orderBy('name')->get(['id', 'name']),
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

    /**
     * Adopted cats are excluded — picking one as a "desired cat" here
     * wouldn't mean anything. Loaded in bulk via the statuses relation
     * rather than a per-cat query, same pattern as Public\CatController.
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function adoptableCatOptions(): array
    {
        return Cat::query()
            ->with('statuses')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->reject(fn (Cat $cat) => $cat->status === CatStatus::Adopted->value)
            ->map(fn (Cat $cat) => ['id' => $cat->id, 'name' => $cat->name])
            ->values()
            ->all();
    }
}
