<?php

use App\Models\Page;

it('shares hreflang alternates for every supported locale on a localized page', function () {
    refreshApplicationWithLocale('fr');
    Page::factory()->create(['slug' => 'a-propos', 'is_published' => true]);

    $response = $this->get('/fr/a-propos');

    $response->assertInertia(fn ($page) => $page
        ->where('alternateUrls.fr', url('/fr/a-propos'))
        ->where('alternateUrls.en', url('/en/a-propos'))
    );
});

it('shares no alternates for a non-localized route like the login page', function () {
    $response = $this->get('/login');

    $response->assertInertia(fn ($page) => $page->where('alternateUrls', []));
});
