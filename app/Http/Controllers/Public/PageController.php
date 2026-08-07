<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Cat;
use App\Models\FaqItem;
use App\Models\Page;
use App\Models\Testimonial;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PageController extends Controller
{
    /**
     * CMS page by slug. Deliberately NOT a route-model-bound wildcard
     * (`/{page:slug}`): when mcamara/laravel-localization's boot-time
     * locale resolution comes up empty (happens outside a real /fr or
     * /en request — e.g. console, or any test that doesn't force a
     * locale), the locale group's prefix collapses to nothing, and a
     * wildcard segment route would then greedily match literally any
     * single-segment path — /login, /register, /profile included —
     * before Laravel ever reaches those real routes. Explicit literal
     * routes (see routes/web.php) can't have that problem: they only
     * ever match their own exact string.
     */
    public function show(Request $request, string $slug): Response
    {
        $page = Page::query()->where('slug', $slug)->firstOrFail();

        abort_if(! $page->is_published, SymfonyResponse::HTTP_NOT_FOUND);

        $props = [
            'page' => [
                'slug' => $page->slug,
                'title' => $page->title,
                'body' => $page->body,
                'meta_title' => $page->meta_title,
                'meta_description' => $page->meta_description,
            ],
        ];

        if ($page->slug === 'a-propos') {
            // Mapped to a plain array rather than passed as a raw Eloquent
            // collection: spatie/laravel-translatable serializes `quote`
            // (translatable) as the full {fr: ..., en: ...} object on
            // toArray()/JSON serialization, not the current-locale string.
            $props['testimonials'] = Testimonial::query()
                ->where('is_published', true)
                ->orderBy('order')
                ->get(['id', 'author_name', 'quote', 'rating'])
                ->map(fn (Testimonial $testimonial) => [
                    'id' => $testimonial->id,
                    'author_name' => $testimonial->author_name,
                    'quote' => $testimonial->quote,
                    'rating' => $testimonial->rating,
                ]);
        }

        if ($page->slug === 'contact') {
            // address/socialLinks come from HandleInertiaRequests' global
            // share now (Page.vue reads them off usePage() directly) —
            // this used to duplicate that same SiteSetting lookup here.

            // "Adopte moi" on a cat's page links to /contact?chaton={slug}
            // to preselect it in the form.
            $props['prefilledCat'] = $request->filled('chaton')
                ? Cat::query()->where('slug', $request->query('chaton'))->first(['id', 'name'])
                : null;
        }

        if ($page->slug === 'faq') {
            // faq_items has no is_published flag (see CLAUDE.md) — every row
            // is public. Same map-to-plain-array reasoning as testimonials
            // above: property access resolves the current locale, raw
            // toArray() would not.
            $props['faqItems'] = FaqItem::query()
                ->orderBy('order')
                ->get(['id', 'question', 'answer'])
                ->map(fn (FaqItem $item) => [
                    'id' => $item->id,
                    'question' => $item->question,
                    'answer' => $item->answer,
                ]);
        }

        return Inertia::render('Public/Page', $props);
    }
}
