<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\Finance\FinancialSecurityService;

class EnsureFinancialAuth
{
    protected FinancialSecurityService $securityService;

    public function __construct(FinancialSecurityService $securityService)
    {
        $this->securityService = $securityService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Expecting the token in a custom header (e.g., X-Finance-Token)
        $token = $request->header('X-Finance-Token');
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if (!$token) {
            return response()->json(['message' => 'Financial authorization token is required.'], 403);
        }

        if (!$this->securityService->validateToken($user->id, $token)) {
            return response()->json(['message' => 'Invalid or expired financial authorization token.'], 403);
        }

        // If the action is a one-off (like a withdrawal), the controller itself might revoke the token after success.
        // We inject the token into the request for potential consumption.
        $request->attributes->add(['finance_token' => $token]);

        return $next($request);
    }
}
