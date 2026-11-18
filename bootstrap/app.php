<?php

use App\Http\Middleware\ShowPublicBookingSummary;
use App\Support\RecoverTeamScopedRecordUrl;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prependToPriorityList(AuthenticatesRequests::class, ShowPublicBookingSummary::class);
        $middleware->validateCsrfTokens(except: [
            'mail-preferences/*/unsubscribe/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            $url = app(RecoverTeamScopedRecordUrl::class)->url($request);

            if (is_string($url)) {
                return redirect()->to($url, 301);
            }
        });
    })->create();
