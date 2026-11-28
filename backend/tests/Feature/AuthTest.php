<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Testing\TestResponse;

function login(string $email, string $password = 'password'): TestResponse
{
    return test()->postJson('/api/v1/auth/login', ['email' => $email, 'password' => $password, 'device_name' => 'test']);
}

it('signs in with valid credentials and returns a token', function () {
    $user = User::factory()->create(['email' => 'adviser@demo.test']);

    login('adviser@demo.test')
        ->assertOk()
        ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role']])
        ->assertJsonPath('user.role', 'teacher');

    expect($user->fresh()->last_login_at)->not->toBeNull()
        ->and(AuditLog::where('action', 'login')->count())->toBe(1);
});

it('rejects a wrong password with a generic 401', function () {
    User::factory()->create(['email' => 'adviser@demo.test']);

    login('adviser@demo.test', 'wrong')->assertUnauthorized()
        ->assertJsonPath('message', 'These credentials do not match our records.');
    login('nobody@demo.test')->assertUnauthorized()
        ->assertJsonPath('message', 'These credentials do not match our records.');
});

it('locks out after 5 failed attempts with 429', function () {
    User::factory()->create(['email' => 'adviser@demo.test']);

    foreach (range(1, 5) as $_) {
        login('adviser@demo.test', 'wrong')->assertUnauthorized();
    }

    login('adviser@demo.test')->assertStatus(429)->assertHeader('Retry-After');
});

it('refuses deactivated and locked accounts', function () {
    User::factory()->create(['email' => 'off@demo.test', 'is_active' => false]);
    User::factory()->create(['email' => 'locked@demo.test', 'locked_until' => now()->addHour()]);

    login('off@demo.test')->assertForbidden();
    login('locked@demo.test')->assertForbidden();
});

it('revokes existing tokens when an account is deactivated', function () {
    $user = User::factory()->create(['email' => 'adviser@demo.test']);
    $token = login('adviser@demo.test')->json('token');

    $user->update(['is_active' => false]);

    $this->withToken($token)->getJson('/api/v1/auth/me')->assertForbidden();
    expect($user->tokens()->count())->toBe(0);
});

it('returns the current user with advised sections', function () {
    [$adviser, $section] = classroom();
    $token = $adviser->createToken('test')->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('id', $adviser->id)
        ->assertJsonPath('advised_sections.0.label', '8-Rizal');
});

it('logs out by revoking the token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();
    expect($user->tokens()->count())->toBe(0);
});

it('requires a token for protected routes', function () {
    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    $this->getJson('/api/v1/anecdotes')->assertUnauthorized();
});
