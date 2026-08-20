<?php

namespace Tests\Feature;

use Tests\TestCase;

class ReviewerConfigurationTest extends TestCase
{
    public function test_reviewer_matrix_matches_the_approved_assignments(): void
    {
        $this->assertSame('3862311015', config('kpi.criterion_reviewers')['2.1.3']);
        $this->assertArrayNotHasKey('2.1.3', config('kpi.ai_human_review_criterion_reviewers'));

        $assignments = array_map(
            'intval',
            [
                ...config('kpi.ai_human_review_criterion_reviewers'),
                ...config('kpi.criterion_reviewers'),
            ],
        );

        $expected = [
            '1.2' => 3862011037,
            '1.3' => 3862011037,
            '1.4' => 3862011037,
            '1.6' => 3862011037,
            '1.7' => 3462612025,
            '1.8' => 3862011037,
            '2.1.1' => 3462111204,
            '2.1.2' => 3462611061,
            '2.1.3' => 3862311015,
            '2.1.4' => 3462611061,
            '2.1.5' => 3462611061,
            '2.1.6' => 3462611061,
            '3.1.1' => 3462011207,
            '3.1.2' => 3462011207,
            '3.1.4' => 3462011207,
            '3.1.5' => 3462011207,
            '3.1.6' => 3462011207,
            '3.1.7' => 3462011207,
            '3.1.8' => 3462011188,
            '3.1.9' => 3462011188,
            '3.1.10' => 3462011207,
            '3.1.11' => 3462111204,
            '3.1.12' => 3462111204,
            '3.1.13' => 3462011188,
            '3.1.14' => 3462011188,
            '3.1.15' => 3462011207,
            '4.1.2' => 3462211323,
            '4.1.3' => 3462211323,
            '4.1.4' => 3462211323,
            '4.1.5' => 3462211323,
        ];

        foreach ($expected as $criterionCode => $hemisId) {
            $this->assertSame($hemisId, $assignments[$criterionCode]);
        }
    }
}
