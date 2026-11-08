<?php

use App\Models\User;

// Accounts are created by an admin; nobody on the network can sign up.

test('registration screen is not available', function () {
    $this->get('/register')->assertNotFound();
});

test('nobody can register a new account', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertNotFound();
    $this->assertGuest();
    expect(User::where('email', 'test@example.com')->exists())->toBeFalse();
});
