<?php

/**
 * Offline LAN: set OFFLINE_ASSETS=true in .env on servers without internet.
 * Uses local axios/fonts and a pre-built Tailwind CSS (same layout as CDN mode).
 */
return [
    'use_local' => filter_var(env('OFFLINE_ASSETS', false), FILTER_VALIDATE_BOOL),

    'axios' => env('OFFLINE_ASSETS', false)
        ? 'assets/vendor/axios.min.js'
        : 'https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js',

    'tailwind_cdn' => 'https://cdn.tailwindcss.com',

    'tailwind_css' => 'assets/css/tailwind-offline.css',
];
