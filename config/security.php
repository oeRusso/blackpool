<?php

return [

    /*
    | Content-Security-Policy. Scripts must carry the per-request nonce (Vite and
    | the inline theme script do). Embeds are limited to YouTube and Vimeo.
    */
    'csp' => [
        'enabled' => (bool) env('SECURITY_CSP_ENABLED', env('APP_ENV') !== 'local'),
        'report_only' => (bool) env('SECURITY_CSP_REPORT_ONLY', false),
    ],

    'hsts' => (bool) env('SECURITY_HSTS', env('APP_ENV') === 'production'),

];
