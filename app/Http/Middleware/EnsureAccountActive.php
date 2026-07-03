<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountActive
{
    /**
     * Only approved accounts reach full role capabilities. Everyone else is
     * sent back to their role's onboarding flow.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->isApproved()) {
            return redirect($user->role->onboardingPath());
        }

        return $next($request);
    }
}
