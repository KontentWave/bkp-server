<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClientVersionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function middleware_tolerates_requests_without_version_headers(): void
    {
        $response = $this->postJson('/api/landlords/tokens', []);

        $response->assertStatus(422);
    }

    #[Test]
    public function middleware_rejects_outdated_android_clients(): void
    {
        Config::set('client_versions.platforms.android.min_supported_version', '1.0.0');
        Config::set('client_versions.platforms.android.latest_version', '1.1.0');
        Config::set('client_versions.platforms.android.download_url', 'https://example.invalid/android.apk');
        Config::set('client_versions.platforms.android.force_update', true);

        $response = $this->withHeaders([
            'X-App-Platform' => 'android',
            'X-App-Version' => '0.9.9',
        ])->postJson('/api/landlords/tokens', []);

        $response->assertStatus(426)
            ->assertJsonPath('code', 'client_outdated')
            ->assertJsonPath('force_update', true)
            ->assertJsonPath('min_supported_version', '1.0.0')
            ->assertJsonPath('latest_version', '1.1.0')
            ->assertJsonPath('download_url', 'https://example.invalid/android.apk');
    }

    #[Test]
    public function middleware_allows_supported_ios_clients_to_reach_the_controller(): void
    {
        Config::set('client_versions.platforms.ios.min_supported_version', '1.0.0');

        $response = $this->withHeaders([
            'X-App-Platform' => 'ios',
            'X-App-Version' => '1.0.0',
        ])->postJson('/api/landlords/tokens', []);

        $response->assertStatus(422);
    }
}
