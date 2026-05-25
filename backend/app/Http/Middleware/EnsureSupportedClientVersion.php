<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSupportedClientVersion
{
    public function handle(Request $request, Closure $next): Response
    {
        $platform = strtolower(trim((string) $request->header('X-App-Platform', '')));
        $version = trim((string) $request->header('X-App-Version', ''));

        if ($platform === '' || $version === '') {
            return $next($request);
        }

        $policy = config("client_versions.platforms.{$platform}");

        if (! is_array($policy)) {
            return $next($request);
        }

        $minSupportedVersion = trim((string) ($policy['min_supported_version'] ?? ''));
        $latestVersion = trim((string) ($policy['latest_version'] ?? ''));
        $downloadUrl = trim((string) ($policy['download_url'] ?? ''));
        $forceUpdate = (bool) ($policy['force_update'] ?? true);

        if (! $this->isValidVersion($version) || ! $this->isValidVersion($minSupportedVersion)) {
            return $next($request);
        }

        if (version_compare($version, $minSupportedVersion, '>=')) {
            return $next($request);
        }

        return response()->json([
            'code' => 'client_outdated',
            'force_update' => $forceUpdate,
            'min_supported_version' => $minSupportedVersion,
            'latest_version' => $this->isValidVersion($latestVersion)
                ? $latestVersion
                : $minSupportedVersion,
            'download_url' => $downloadUrl !== '' ? $downloadUrl : null,
            'message' => 'Prepac, tvoja verzia aplikacie je uz zastarala. Nainstaluj najnovsiu verziu aplikacie.',
        ], JsonResponse::HTTP_UPGRADE_REQUIRED);
    }

    private function isValidVersion(string $value): bool
    {
        return preg_match('/^\d+\.\d+\.\d+$/', $value) === 1;
    }
}
