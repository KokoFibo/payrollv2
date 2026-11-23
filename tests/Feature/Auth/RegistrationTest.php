<?php

test('public registration is disabled for the payroll application', function () {
    $this->get('/register')->assertNotFound();
});

test('public registration submissions are rejected', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertGuest();
    $response->assertNotFound();
});
