<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTestimonialRequest;
use App\Http\Requests\Admin\UpdateTestimonialRequest;
use App\Models\Testimonial;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TestimonialController extends Controller
{
    public function index(): Response
    {
        $testimonials = Testimonial::query()->orderBy('order')->paginate(20)->withQueryString();

        return Inertia::render('Admin/Testimonials/Index', [
            'testimonials' => $testimonials,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Testimonials/Form');
    }

    public function store(StoreTestimonialRequest $request): RedirectResponse
    {
        Testimonial::create($request->validated());

        return redirect()->route('admin.testimonials.index')->with('success', 'Témoignage créé.');
    }

    public function edit(Testimonial $testimonial): Response
    {
        return Inertia::render('Admin/Testimonials/Form', [
            'testimonial' => [
                'id' => $testimonial->id,
                'author_name' => $testimonial->author_name,
                'quote' => $testimonial->getTranslations('quote'),
                'rating' => $testimonial->rating,
                'is_published' => $testimonial->is_published,
                'order' => $testimonial->order,
            ],
        ]);
    }

    public function update(UpdateTestimonialRequest $request, Testimonial $testimonial): RedirectResponse
    {
        $testimonial->update($request->validated());

        return redirect()->route('admin.testimonials.index')->with('success', 'Témoignage mis à jour.');
    }

    public function destroy(Testimonial $testimonial): RedirectResponse
    {
        $testimonial->delete();

        return redirect()->route('admin.testimonials.index')->with('success', 'Témoignage supprimé.');
    }
}
