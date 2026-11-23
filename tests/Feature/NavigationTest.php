<?php

use App\Models\User;

test('standalone department and position pages have been removed', function () {
    $admin = User::factory()->create(['role' => 5]);

    $this->actingAs($admin)->get('/admin/departments')->assertNotFound();
    $this->actingAs($admin)->get('/admin/positions')->assertNotFound();
});
