<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CatSex;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLitterRequest;
use App\Http\Requests\Admin\UpdateLitterRequest;
use App\Models\Cat;
use App\Models\Litter;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class LitterController extends Controller
{
    public function index(): Response
    {
        $litters = Litter::query()
            ->with(['sire', 'dam'])
            ->withCount('kittens')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Litters/Index', [
            'litters' => $litters,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Litters/Form', [
            'sires' => Cat::query()->where('sex', CatSex::Male)->get(['id', 'name']),
            'dams' => Cat::query()->where('sex', CatSex::Female)->get(['id', 'name']),
        ]);
    }

    public function store(StoreLitterRequest $request): RedirectResponse
    {
        Litter::create($request->validated());

        return redirect()->route('admin.litters.index')->with('success', 'Portée créée.');
    }

    public function edit(Litter $litter): Response
    {
        return Inertia::render('Admin/Litters/Form', [
            'litter' => $litter->load(['sire', 'dam']),
            'sires' => Cat::query()->where('sex', CatSex::Male)->get(['id', 'name']),
            'dams' => Cat::query()->where('sex', CatSex::Female)->get(['id', 'name']),
        ]);
    }

    public function update(UpdateLitterRequest $request, Litter $litter): RedirectResponse
    {
        $litter->update($request->validated());

        return redirect()->route('admin.litters.index')->with('success', 'Portée mise à jour.');
    }

    public function destroy(Litter $litter): RedirectResponse
    {
        $litter->delete();

        return redirect()->route('admin.litters.index')->with('success', 'Portée supprimée.');
    }
}
