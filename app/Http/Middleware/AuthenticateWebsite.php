<?php

namespace App\Http\Middleware;

use App\Models\WebsiteConnection;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets a website in only with its own active key, sent from its server,
 * and limits how many sign-ups per minute each website (and each visitor,
 * when the website passes on X-Visitor-Ip) can send.
 */
class AuthenticateWebsite
{
    public function handle(Request $request, Closure $next): Response
    {
        // Wrong keys are limited per calling address, to stop guessing.
        $guessKey = 'website-key-fail:'.$request->ip();
        if (RateLimiter::tooManyAttempts($guessKey, 20)) {
            return response()->json(['message' => 'Too many attempts. Try again later.'], 429);
        }

        $key = $request->bearerToken() ?? $request->header('X-Api-Key');
        $conn = WebsiteConnection::findActiveByKey($key);

        if (! $conn || ! $conn->source?->is_active) {
            RateLimiter::hit($guessKey, 600);

            return response()->json(['message' => 'Invalid or switched-off key.'], 401);
        }

        $limits = ['site:'.$conn->id => $conn->per_minute_limit];
        if ($visitor = $request->header('X-Visitor-Ip')) {
            $limits['visitor:'.$conn->id.':'.$visitor] = 5;
        }
        foreach ($limits as $bucket => $max) {
            if (RateLimiter::tooManyAttempts('website-leads:'.$bucket, $max)) {
                return response()->json(['message' => 'Too many sign-ups. Please try again in a minute.'], 429)
                    ->header('Retry-After', RateLimiter::availableIn('website-leads:'.$bucket));
            }
        }
        foreach (array_keys($limits) as $bucket) {
            RateLimiter::hit('website-leads:'.$bucket, 60);
        }

        $request->attributes->set('website_connection', $conn);

        return $next($request);
    }
}
