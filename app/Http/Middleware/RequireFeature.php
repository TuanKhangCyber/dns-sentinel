<?php

namespace App\Http\Middleware;

use App\Exceptions\FeatureAccessException;
use App\Services\FeatureAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireFeature
{
    public function __construct(private readonly FeatureAccessService $features) {}

    public function handle(Request $request, Closure $next, string $code): Response
    {
        try {
            $this->features->authorize($request->user(), $code);
        } catch (FeatureAccessException $exception) {
            return $request->expectsJson()
                ? response()->json(['success' => false, 'error_code' => $exception->errorCode, 'error' => $exception->getMessage()], $exception->httpStatus)
                : abort($exception->httpStatus, $exception->getMessage());
        }

        return $next($request);
    }
}
