<?php

use App\Console\Commands\SyncProductionFlatsCommand;
use App\Http\Middleware\EnsureSupportedClientVersion;
use App\Http\Middleware\VerifyHardwareSignature;
use App\Http\Middleware\VerifyTranslationWorkerToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        SyncProductionFlatsCommand::class,
        __DIR__.'/../app/Console/Commands',
    ])
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['middleware' => ['auth:sanctum', 'hardware.signature']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'client.version' => EnsureSupportedClientVersion::class,
            'hardware.signature' => VerifyHardwareSignature::class,
            'translation.worker' => VerifyTranslationWorkerToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
