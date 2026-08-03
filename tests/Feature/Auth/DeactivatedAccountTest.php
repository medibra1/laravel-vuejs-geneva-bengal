<?php

use App\Models\User;

it('refuses login for a deactivated account', function () {
    $user = User::factory()->create([
        'password' => bcrypt('password'),
        'is_active' => false,
    ]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('records last_login_at on successful login', function () {
    $user = User::factory()->create([
        'password' => bcrypt('password'),
        'is_active' => true,
        'last_login_at' => null,
    ]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    expect($user->fresh()->last_login_at)->not->toBeNull();
});
