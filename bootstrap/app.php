<?php

use App\Exceptions\FeatureAccessException;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\RequireFeature;
use App\Http\Middleware\SetLocale;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [SetLocale::class]);
        $middleware->alias(['active-user' => EnsureActiveUser::class, 'admin' => EnsureAdmin::class, 'feature' => RequireFeature::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $scannerError = static fn (Request $request): bool => $request->is('scanner/scans*') && $request->expectsJson();
        $payload = static fn (string $message, array $errors = []): array => [
            'success' => false, 'scan_id' => null, 'status' => null, 'progress' => 0, 'stage' => null,
            'data' => [], 'warnings' => [], 'error' => $message, 'errors' => $errors,
            'created_at' => null, 'updated_at' => now()->toIso8601String(),
        ];
        $exceptions->render(fn (ValidationException $exception, Request $request) => $scannerError($request)
            ? response()->json($payload($exception->getMessage(), $exception->errors()), 422) : null);
        $exceptions->render(fn (AuthenticationException $exception, Request $request) => $scannerError($request)
            ? response()->json($payload(__('Unauthenticated.')), 401) : null);
        $exceptions->render(fn (AuthorizationException $exception, Request $request) => $scannerError($request)
            ? response()->json($payload(__('This action is unauthorized.')), 403) : null);
        $exceptions->render(fn (ModelNotFoundException $exception, Request $request) => $scannerError($request)
            ? response()->json($payload(__('Not Found.')), 404) : null);
        $exceptions->render(fn (FeatureAccessException $exception, Request $request) => $request->expectsJson()
            ? response()->json(['success' => false, 'error_code' => $exception->errorCode, 'error' => $exception->getMessage()], $exception->httpStatus)
            : response($exception->getMessage(), $exception->httpStatus));
    })->create();
