<?php

return [
    'ai_unlimited_submission_max_point' => (float) env('KPI_AI_UNLIMITED_SUBMISSION_MAX_POINT', 1),
    'report_period_start' => '2025-09-01',
    'report_period_end' => '2026-08-31',
    'ai_status_viewer_hemis_id' => env('KPI_AI_STATUS_VIEWER_HEMIS_ID', '3172011004'),
    'settings_manager_hemis_id' => env('KPI_SETTINGS_MANAGER_HEMIS_ID', '3172011004'),
    'criterion_reviewers' => [
        '1.1' => env('KPI_REVIEWER_1_1_HEMIS_ID', '3172011004'),
        '1.5' => env('KPI_REVIEWER_1_5_HEMIS_ID', '3862011037'),
        '1.6' => env('KPI_REVIEWER_1_6_HEMIS_ID', '3862311015'),
        '1.7' => env('KPI_REVIEWER_1_7_HEMIS_ID', '3172011004'),
        '2.1.3' => env('KPI_REVIEWER_2_1_3_HEMIS_ID', '3862311015'),
        '2.1.4' => env('KPI_REVIEWER_2_1_4_HEMIS_ID', '3462611061'),
        '3.1.4' => env('KPI_REVIEWER_3_1_4_HEMIS_ID', '3462011207'),
        '3.1.6' => env('KPI_REVIEWER_3_1_6_HEMIS_ID', '3462011207'),
        '3.1.7' => env('KPI_REVIEWER_3_1_7_HEMIS_ID', '3462011207'),
        '4.1.6' => env('KPI_REVIEWER_4_1_6_HEMIS_ID', '3461612013'),
    ],
    'ai_queue_stale_after_minutes' => 10,
    'ai_requests_per_minute' => (int) env('KPI_AI_REQUESTS_PER_MINUTE', 10),
];
