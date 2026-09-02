<?php

use App\Models\User;

it('logs in with valid credentials and returns a token', function () {
    $user = User::factory()->admin()->create(['password' => bcrypt('secret123')]);

    $response = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'secret123',
    ]);

    $response->assertOk()->assertJsonStructure(['token', 'user' => ['id', 'email', 'role']]);
});

it('rejects invalid credentials', function () {
    $user = User::factory()->create(['password' => bcrypt('secret123')]);

    $response = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(422);
});

it('blocks unauthenticated access to protected routes', function () {
    $this->getJson('/api/auth/me')->assertStatus(401);
});
