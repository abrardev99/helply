<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | The public widget endpoint (POST /api/widget/{agent}/chat) manages its own
    | CORS in the VerifyWidgetOrigin middleware, reflecting only origins that are
    | in the agent's allow-list. We therefore keep the framework's global CORS
    | handler disabled (empty paths) so it never blanket-allows every origin ("*")
    | and override our per-agent headers. No other route is cross-origin.
    |
    */

    'paths' => [],

    'allowed_methods' => ['*'],

    'allowed_origins' => [],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
