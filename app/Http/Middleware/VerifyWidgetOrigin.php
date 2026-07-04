<?php

namespace App\Http\Middleware;

use App\Models\Agent;
use Closure;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class VerifyWidgetOrigin
{
    /**
     * Reject widget requests whose Origin/Referer is not in the agent's allow-list, and
     * reflect only an allow-listed origin back in the CORS headers. Also answers the
     * browser's CORS preflight (OPTIONS) request.
     *
     * Note: Origin is browser-enforced, not a hard security boundary (a script can forge
     * it). It ties usage to the customer's domains and stops casual cross-site embedding;
     * the rate limiter is the real abuse control.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $agent = $request->route('agent');

        $origin = $this->requestOrigin($request);
        $allowed = $agent instanceof Agent ? ($agent->embed_origins ?? []) : [];
        $originIsAllowed = $origin !== null && in_array($origin, $allowed, true);

        // CORS preflight: never reaches the controller. Approve only allow-listed origins.
        if ($request->getMethod() === 'OPTIONS') {
            $response = response()->noContent();

            if ($originIsAllowed) {
                $this->applyCorsHeaders($response, $origin);
            }

            return $response;
        }

        if (! $originIsAllowed) {
            abort(403, __('This origin is not allowed to use this agent.'));
        }

        // Even if the request errors (e.g. a misconfigured agent), the response must carry
        // CORS headers so a backend failure surfaces as a real error to the widget rather
        // than masquerading as a CORS block.
        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $handler = app(ExceptionHandler::class);
            $handler->report($exception);
            $response = $handler->render($request, $exception);
        }

        $this->applyCorsHeaders($response, $origin);

        return $response;
    }

    /**
     * Reflect the (already allow-listed) origin and declare the allowed methods/headers.
     */
    private function applyCorsHeaders(Response $response, string $origin): void
    {
        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Allow-Methods', 'POST, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Accept');
        $response->headers->set('Access-Control-Max-Age', '86400');
        $response->headers->set('Vary', 'Origin');
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
