<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Daily Message Cap
    |--------------------------------------------------------------------------
    |
    | A coarse per-agent ceiling on public widget chat messages per day. It sits
    | on top of the per-IP, per-minute limiter and bounds a single agent's OpenAI
    | spend against distributed clients (many IPs) that the per-IP limit misses.
    |
    */

    'daily_cap' => (int) env('WIDGET_DAILY_CAP', 2000),

];
