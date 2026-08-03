<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGalleryRequest;
use App\Http\Requests\Admin\UpdateGalleryRequest;
use App\Http\Resources\GalleryResource;
use App\Models\Gallery;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class GalleryController extends Controller
{
    public function index(): Response
    {
        $galleries = Gallery::query()->with('media')->orderBy('position')->paginate(20)->withQueryString();

        $galleries->through(fn (Gallery $gallery) => GalleryResource::make($gallery)->resolve());

        return Inertia::render('Admin/Galleries/Index', [
            'galleries' => $galleries,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Galleries/Form');
    }

    public function store(StoreGalleryRequest $request): RedirectResponse
    {
        $gallery = Gallery::create($request->safe()->except('image'));

        $gallery->addMedia($request->file('image'))->toMediaCollection('image');

        return redirect()->route('admin.galleries.index')->with('success', __('Photo added.'));
    }

    public function edit(Gallery $gallery): Response
    {
        return Inertia::render('Admin/Galleries/Form', [
            'gallery' => GalleryResource::make($gallery->load('media')),
        ]);
    }

    public function update(UpdateGalleryRequest $request, Gallery $gallery): RedirectResponse
    {
        $gallery->update($request->safe()->except('image'));

        if ($request->hasFile('image')) {
            $gallery->addMedia($request->file('image'))->toMediaCollection('image');
        }

        return redirect()->route('admin.galleries.index')->with('success', __('Photo updated.'));
    }

    public function destroy(Gallery $gallery): RedirectResponse
    {
        $gallery->delete();

        return redirect()->route('admin.galleries.index')->with('success', __('Photo deleted.'));
    }
}
