<?php

return [
    'api_key' => env('GEMINI_API_KEY'),
    'base_url' => env('GEMINI_BASE_URL'),
    'request_timeout' => (int) env('GEMINI_REQUEST_TIMEOUT', 45),
];
