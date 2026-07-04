<?php

namespace App\Http\Middleware;

use App\Models\Bot;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWidgetOrigin
{
    /**
     * Reject widget requests whose Origin/Referer is not in the bot's allow-list, and
     * reflect only an allow-listed origin back in the CORS headers.
     *
     * Note: Origin is browser-enforced, not a hard security boundary (a script can forge
     * it). It ties usage to the customer's domains and stops casual cross-site embedding;
     * the rate limiter is the real abuse control.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $bot = $request->route('bot');

        $origin = $this->requestOrigin($request);
        $allowed = $bot instanceof Bot ? ($bot->embed_origins ?? []) : [];

        if ($origin === null || ! in_array($origin, $allowed, true)) {
            abort(403, __('This origin is not allowed to use this bot.'));
        }

        $response = $next($request);

        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Vary', 'Origin');

        return $response;
    }

    /**
     * The request's origin, taken from the Origin header or derived from the Referer.
     */
    private function requestOrigin(Request $request): ?string
    {
        if ($request->headers->has('Origin')) {
            return $request->headers->get('Origin');
        }

        $referer = $request->headers->get('Referer');

        if ($referer === null) {
            return null;
        }

        $parts = parse_url($referer);

        if (! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $origin = $parts['scheme'].'://'.$parts['host'];

        return isset($parts['port']) ? $origin.':'.$parts['port'] : $origin;
    }
}
