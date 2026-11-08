<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * In the public demo, shared accounts must stay usable by every visitor.
 */
class ProtectDemoAccounts
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('demo.enabled') || $request->isMethodSafe() || ! $this->targetsDemoAccount($request)) {
            return $next($request);
        }

        $message = __('This action is disabled for the shared demo accounts. Register your own account to try it.');

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

        return back();
    }

    private function targetsDemoAccount(Request $request): bool
    {
        if (! in_array($request->route()?->getName(), config('demo.protected_routes'), true)) {
            return false;
        }

        $email = $request->user()->email ?? $request->input('email');

        return in_array(strtolower((string) $email), config('demo.accounts'), true);
    }
}
