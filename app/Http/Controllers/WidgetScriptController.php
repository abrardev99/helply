<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class WidgetScriptController extends Controller
{
    /**
     * Serve the self-contained embeddable widget loader as JavaScript.
     *
     * Served via a route (rather than a static public/ file) so the app's base URL can be
     * injected and the embed contract can be covered by a feature test.
     */
    public function __invoke(): Response
    {
        return response()
            ->view('widget')
            ->header('Content-Type', 'application/javascript; charset=utf-8')
            ->header('Cache-Control', 'public, max-age=300');
    }
}
