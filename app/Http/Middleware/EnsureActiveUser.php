<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isSuspended()) {
            return $request->expectsJson()
                ? response()->json(['success' => false, 'error_code' => 'account_suspended', 'error' => __('platform.errors.account_suspended')], 403)
                : abort(403, __('platform.errors.account_suspended'));
        }

        return $next($request);
    }
}
