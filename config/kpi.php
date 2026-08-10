<?php

$scientificPublicationsReviewerHemisId = env(
    'KPI_AI_HUMAN_REVIEWER_SCIENTIFIC_PUBLICATIONS_HEMIS_ID',
    '3462011207',
);
$educationalLiteratureReviewerHemisId = env(
    'KPI_AI_HUMAN_REVIEWER_EDUCATIONAL_LITERATURE_HEMIS_ID',
    '3862011037',
);
$fixedPerResourceReviewerHemisId = env(
    'KPI_AI_HUMAN_REVIEWER_FIXED_RESOURCE_HEMIS_ID',
    '3462211323',
);
$industryFundingAndUniversityProjectReviewerHemisId = env(
    'KPI_AI_HUMAN_REVIEWER_INDUSTRY_FUNDING_AND_PROJECT_HEMIS_ID',
    '3462011188',
);
$resourceStatisticsViewerHemisIds = array_values(array_filter(
    array_map(
        'trim',
        explode(',', (string) env('KPI_RESOURCE_STATISTICS_VIEWER_HEMIS_IDS', '3862011004')),
    ),
));
$superAdminHemisIds = array_values(array_filter(
    array_map(
        'trim',
        explode(',', (string) env('KPI_SUPER_ADMIN_HEMIS_IDS', '3172011004')),
    ),
));
$primarySuperAdminHemisId = $superAdminHemisIds[0] ?? null;

return [
    'super_admin_hemis_ids' => $superAdminHemisIds,
    'ai_unlimited_submission_max_point' => (float) env('KPI_AI_UNLIMITED_SUBMISSION_MAX_POINT', 1),
    'resource_upload_deadline' => env('KPI_RESOURCE_UPLOAD_DEADLINE', '2026-08-15 23:59:59'),
    'report_period_start' => '2025-09-01',
    'report_period_end' => '2026-08-31',
    'ai_status_viewer_hemis_id' => env('KPI_AI_STATUS_VIEWER_HEMIS_ID', $primarySuperAdminHemisId),
    'ai_operations_manager_hemis_id' => env('KPI_AI_OPERATIONS_MANAGER_HEMIS_ID', $primarySuperAdminHemisId),
    'resource_statistics_viewer_hemis_ids' => $resourceStatisticsViewerHemisIds,
    'settings_manager_hemis_id' => env('KPI_SETTINGS_MANAGER_HEMIS_ID', $primarySuperAdminHemisId),
    'accepted_ai_reviewer_hemis_id' => env('KPI_ACCEPTED_AI_REVIEWER_HEMIS_ID', $primarySuperAdminHemisId),
    'foreign_language_faculty_department_id' => (int) env('KPI_FOREIGN_LANGUAGE_FACULTY_DEPARTMENT_ID', 1),
    'russian_language_department_id' => (int) env('KPI_RUSSIAN_LANGUAGE_DEPARTMENT_ID', 23),
    'ai_human_review_criterion_reviewers' => [
        '1.2' => $educationalLiteratureReviewerHemisId,
        '1.3' => $educationalLiteratureReviewerHemisId,
        '1.4' => $educationalLiteratureReviewerHemisId,
        '1.10' => $educationalLiteratureReviewerHemisId,
        '2.1.1' => env('KPI_AI_HUMAN_REVIEWER_2_1_1_HEMIS_ID', '3462011207'),
        '2.1.6' => env('KPI_AI_HUMAN_REVIEWER_2_1_6_HEMIS_ID', '3462611061'),
        '3.1.1' => $scientificPublicationsReviewerHemisId,
        '3.1.2' => $scientificPublicationsReviewerHemisId,
        '3.1.3' => $scientificPublicationsReviewerHemisId,
        '3.1.8' => $scientificPublicationsReviewerHemisId,
        '3.1.10' => $fixedPerResourceReviewerHemisId,
        '3.1.12' => $fixedPerResourceReviewerHemisId,
        '3.1.13' => $industryFundingAndUniversityProjectReviewerHemisId,
        '3.1.14' => $industryFundingAndUniversityProjectReviewerHemisId,
        '3.1.15' => $scientificPublicationsReviewerHemisId,
        '4.1.1' => env('KPI_AI_HUMAN_REVIEWER_4_1_1_HEMIS_ID', $primarySuperAdminHemisId),
        '4.1.2' => $fixedPerResourceReviewerHemisId,
        '4.1.3' => $fixedPerResourceReviewerHemisId,
        '4.1.4' => $fixedPerResourceReviewerHemisId,
        '4.1.5' => $fixedPerResourceReviewerHemisId,
    ],
    'criterion_reviewers' => [
        '1.1' => env('KPI_REVIEWER_1_1_HEMIS_ID', $primarySuperAdminHemisId),
        '1.7' => env('KPI_REVIEWER_1_7_HEMIS_ID', $primarySuperAdminHemisId),
        '2.1.3' => env('KPI_REVIEWER_2_1_3_HEMIS_ID', '3862311015'),
        '2.1.4' => env('KPI_REVIEWER_2_1_4_HEMIS_ID', '3462611061'),
        '3.1.4' => env('KPI_REVIEWER_3_1_4_HEMIS_ID', '3462011207'),
        '3.1.6' => env('KPI_REVIEWER_3_1_6_HEMIS_ID', '3462011207'),
        '3.1.7' => env('KPI_REVIEWER_3_1_7_HEMIS_ID', '3462011207'),
        '4.1.6' => env('KPI_REVIEWER_4_1_6_HEMIS_ID', '3461612013'),
    ],
    'ai_queue_stale_after_minutes' => 10,
    'ai_worker_stale_after_seconds' => (int) env('KPI_AI_WORKER_STALE_AFTER_SECONDS', 90),
    'ai_requests_per_minute' => (int) env('KPI_AI_REQUESTS_PER_MINUTE', 10),
    'upload_max_file_size_mb' => 5,
];
