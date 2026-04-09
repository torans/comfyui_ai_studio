<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_log_in_and_receive_token(): void
    {
        $user = User::factory()->create([
            'email' => 'employee@example.com',
            'password' => 'secret123',
            'role' => 'employee',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'employee@example.com',
            'password' => 'secret123',
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role']]);
    }
}
