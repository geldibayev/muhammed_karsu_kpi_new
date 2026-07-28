<?php

return [
    'ai_unlimited_submission_max_point' => (float) env('KPI_AI_UNLIMITED_SUBMISSION_MAX_POINT', 1),
    'ai_status_viewer_hemis_id' => env('KPI_AI_STATUS_VIEWER_HEMIS_ID', '3172011004'),
    'ai_queue_stale_after_minutes' => 10,
    'ai_requests_per_minute' => (int) env('KPI_AI_REQUESTS_PER_MINUTE', 10),
];
