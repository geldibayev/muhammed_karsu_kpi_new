<?php

namespace Tests\Unit;

use App\Support\MasterClassCriterionRule;
use Tests\TestCase;

class MasterClassCriterionRuleTest extends TestCase
{
    /**
     * A basic unit test example.
     */
    public function test_protocol_is_checked_before_dates_and_other_master_class_evidence(): void
    {
        $protocolCheckPosition = mb_strpos(MasterClassCriterionRule::PROMPT, '1-BOSQICH — MAJBURIY PROTOKOLNI TEKSHIRING');
        $dateCheckPosition = mb_strpos(MasterClassCriterionRule::PROMPT, '2-BOSQICH — FAQAT PROTOKOL BORLIGI ANIQLANGANDAN KEYIN');

        $this->assertIsInt($protocolCheckPosition);
        $this->assertIsInt($dateCheckPosition);
        $this->assertLessThan($dateCheckPosition, $protocolCheckPosition);
        $this->assertStringContainsString(
            'protokol yoki bayonnoma mavjud bo\'lmasa, boshqa dalillarni tekshirishni davom ettirmasdan darhol cancelled',
            MasterClassCriterionRule::PROMPT,
        );
        $this->assertStringContainsString(
            'protokolni to\'ldiruvchi dalil bo\'lishi mumkin, lekin protokol o\'rnini bosa olmaydi',
            MasterClassCriterionRule::PROMPT,
        );
        $this->assertStringContainsString('resource_date maydonida YYYY-MM-DD', MasterClassCriterionRule::PROMPT);
        $this->assertStringContainsString('report_period oralig\'ida bo\'lishi shart', MasterClassCriterionRule::PROMPT);
    }
}
