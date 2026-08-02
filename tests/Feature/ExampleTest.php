<?php

it('returns a successful response', function () {
    refreshApplicationWithLocale('fr');

    $response = $this->get('/fr');

    $response->assertStatus(200);
});
