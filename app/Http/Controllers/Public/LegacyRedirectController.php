<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Cat;
use App\Models\Color;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Permanent (301) redirects from the previous Angular site's URLs to their
 * equivalents on this app, so links already indexed by search engines or
 * shared by visitors keep working after the domain switch, and their
 * ranking is transferred instead of lost to a 404.
 *
 * The legacy route table was read from the production Angular bundle
 * (genevabengals.ch, main.*.js): "", "chaton-bengal-a-vendre",
 * "chaton-bengal-a-vendre/:color_slug", "chaton/:slug/:name", "galleries",
 * "newsletter/:abonne", "paiement" (plus login/register, which this app
 * already serves itself). The old site was French-only, so every target is
 * the /fr variant — deliberately not the visitor's detected locale: a 301
 * must point to one stable URL for search engines to transfer it.
 */
class LegacyRedirectController extends Controller
{
    /** Old color slugs were "chaton-bengal-<color>"; ours are just "<color>". */
    private const LEGACY_COLOR_PREFIX = 'chaton-bengal-';

    /** /chaton-bengal-a-vendre and /paiement (reservation now starts from a kitten). */
    public function catsIndex(): RedirectResponse
    {
        return $this->to('/chatons-disponibles');
    }

    /** /chaton-bengal-a-vendre/{colorSlug} → color-filtered list, or the full list if unknown. */
    public function catsByColor(string $colorSlug): RedirectResponse
    {
        $slug = Str::after($colorSlug, self::LEGACY_COLOR_PREFIX);

        return Color::query()->where('slug', $slug)->exists()
            ? $this->to("/chatons-disponibles/couleur/{$slug}")
            : $this->catsIndex();
    }

    /**
     * /chaton/{slug}/{name}. Old slugs were generated from color + sex
     * ("bengal-snow-male-2"), ours from the name — so a cat re-entered in
     * this app is usually only findable by its name. Tries the old slug
     * first (in case it was carried over), then the name, then falls back
     * to the list rather than 404ing on a kitten that no longer exists.
     */
    public function catShow(string $slug, ?string $name = null): RedirectResponse
    {
        $candidates = array_values(array_unique(array_filter([$slug, $name ? Str::slug($name) : null])));

        foreach ($candidates as $candidate) {
            if (Cat::query()->where('slug', $candidate)->exists()) {
                return $this->to("/chatons-disponibles/{$candidate}");
            }
        }

        return $this->catsIndex();
    }

    public function galleries(): RedirectResponse
    {
        return $this->to('/galerie');
    }

    /**
     * /newsletter/{abonnement|desabonnement}-newsletter. The old
     * unsubscribe link carried no token; ours requires one, so the closest
     * equivalent is the homepage (newsletter form in the footer).
     */
    public function newsletter(): RedirectResponse
    {
        return $this->to('');
    }

    private function to(string $path): RedirectResponse
    {
        return redirect()->to(URL::to('/fr'.$path), 301);
    }
}
