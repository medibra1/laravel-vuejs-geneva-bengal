<?php

it('rejects a request without a token', function () {
    config(['app.cron_secret' => 'the-real-secret']);

    $this->get('/cron/run-schedule')->assertForbidden();
});

it('rejects a request with the wrong token', function () {
    config(['app.cron_secret' => 'the-real-secret']);

    $this->get('/cron/run-schedule?token=wrong')->assertForbidden();
});

it('rejects every request when no secret is configured', function () {
    config(['app.cron_secret' => null]);

    $this->get('/cron/run-schedule?token=anything')->assertForbidden();
});

it('runs the scheduler and responds with no content given the correct token', function () {
    config(['app.cron_secret' => 'the-real-secret']);

    $this->get('/cron/run-schedule?token=the-real-secret')->assertNoContent();
});
