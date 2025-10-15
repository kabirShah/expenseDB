<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ResponseTimeMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Set maximum execution time to 2 seconds
        set_time_limit(2);

        // Start timer
        $startTime = microtime(true);

        // Store start time in request for terminate
        $request->merge(['_response_start_time' => $startTime]);

        return $next($request);
    }

    /**
     * Perform actions after the response is sent.
     */
    public function terminate(Request $request, Response $response): void
    {
        $startTime = $request->get('_response_start_time');
        if ($startTime) {
            $endTime = microtime(true);
            $duration = $endTime - $startTime;

            if ($duration > 2) {
                Log::warning('API Response Time Exceeded', [
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'duration' => round($duration, 3) . ' seconds',
                    'user_id' => $request->user() ? $request->user()->id : null,
                ]);
            }
        }
    }
}
