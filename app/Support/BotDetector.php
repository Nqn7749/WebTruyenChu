<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * User-agent check for automated traffic that must not be counted (ad clicks and the like).
 * It is not a strong defence, just enough to filter common crawlers that do not run JavaScript.
 */
class BotDetector
{
    public static function isBot(?string $userAgent): bool
    {
        $agent = Str::lower((string) $userAgent);

        // No real browser sends an empty User-Agent.
        if ($agent === '') {
            return true;
        }

        return Str::contains($agent, [
            'bot', 'spider', 'crawler', 'slurp', 'facebookexternalhit', 'twitterbot', 'linkedinbot', 'pinterest',
            'embedly', 'headless', 'curl', 'wget', 'python-requests', 'go-http-client', 'httpclient',
        ]);
    }
}
