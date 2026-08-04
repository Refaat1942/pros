<?php

/**
 * Offline LAN: local fonts + pre-built Tailwind (no CDN timeouts).
 * Production defaults to local; override with OFFLINE_ASSETS=false for CDN mode.
 */
return [
    'use_local' => filter_var(
        env('OFFLINE_ASSETS', env('APP_ENV') === 'production'),
        FILTER_VALIDATE_BOOL
    ),

    'axios' => 'assets/vendor/axios.min.js',

    'tailwind_cdn' => 'https://cdn.tailwindcss.com',

    'tailwind_css' => 'assets/css/tailwind-offline.css',
];
