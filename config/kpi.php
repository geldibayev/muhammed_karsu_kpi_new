<?php

return [
    'ai_unlimited_submission_max_point' => (float) env('KPI_AI_UNLIMITED_SUBMISSION_MAX_POINT', 1),
    'report_period_start' => env('KPI_REPORT_PERIOD_START', '2025-09-01'),
    'report_period_end' => env('KPI_REPORT_PERIOD_END', '2026-07-31'),
    'ai_status_viewer_hemis_id' => env('KPI_AI_STATUS_VIEWER_HEMIS_ID', '3172011004'),
    'settings_manager_hemis_id' => env('KPI_SETTINGS_MANAGER_HEMIS_ID', '3172011004'),
    'ai_queue_stale_after_minutes' => 10,
    'ai_requests_per_minute' => (int) env('KPI_AI_REQUESTS_PER_MINUTE', 10),
];
