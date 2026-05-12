<?php

namespace Tests\Feature;

use App\Models\Landlord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminLogApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_token_issuance_returns_local_admin_session(): void
    {
        $response = $this->postJson('/api/admins/tokens', [
            'name' => 'local-admin',
            'device_name' => 'admin-console',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.actor_type', 'admin')
            ->assertJsonPath('data.name', 'local-admin');

        $this->assertIsString($response->json('data.token'));
    }

    #[Test]
    public function admin_can_read_backend_logs(): void
    {
        file_put_contents(storage_path('logs/laravel.log'), "first line\nsecond line\n");

        $tokenResponse = $this->postJson('/api/admins/tokens', [
            'name' => 'local-admin',
        ]);

        $response = $this->withToken($tokenResponse->json('data.token'))
            ->getJson('/api/admin/logs');

        $response->assertOk()
            ->assertJsonPath('data.content', "first line\nsecond line\n")
            ->assertJsonPath('data.size_bytes', strlen("first line\nsecond line\n"));
    }

    #[Test]
    public function landlord_cannot_read_backend_logs(): void
    {
        $landlord = Landlord::query()->create([
            'phone_number' => '+421900111222',
            'public_key' => 'PUBLIC-KEY',
            'is_verified' => true,
        ]);

        $token = $landlord->createToken('landlord-device')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/admin/logs');

        $response->assertForbidden()
            ->assertJsonPath('message', 'Only admins can read backend logs.');
    }
}
