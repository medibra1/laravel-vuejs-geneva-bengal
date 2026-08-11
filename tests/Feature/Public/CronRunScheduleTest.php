<?php

it('rejects a request without a token', function () {
    config(['app.cron_secret' => 'the-real-secret']);

    $this->get('/cron/run')->assertForbidden();
});

it('rejects a request with the wrong token', function () {
    config(['app.cron_secret' => 'the-real-secret']);

    $this->get('/cron/run?token=wrong')->assertForbidden();
});

it('rejects every request when no secret is configured', function () {
    config(['app.cron_secret' => null]);

    $this->get('/cron/run?token=anything')->assertForbidden();
});

it('runs the scheduler and the queue worker and responds OK given the correct token', function () {
    config(['app.cron_secret' => 'the-real-secret']);

    $this->get('/cron/run?token=the-real-secret')
        ->assertOk()
        ->assertSee('OK');
});
