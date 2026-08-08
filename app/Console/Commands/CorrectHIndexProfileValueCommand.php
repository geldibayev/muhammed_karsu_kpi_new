<?php

namespace App\Console\Commands;

use App\Actions\CorrectHIndexProfileValue;
use App\Models\User;
use Illuminate\Console\Command;
use Throwable;

class CorrectHIndexProfileValueCommand extends Command
{
    /** @var string */
    protected $signature = 'kpi:h-index:correct
                            {hemis_id : Resurs egasining HEMIS ID si}
                            {expected=9 : Bazadagi kutilgan eski H-index}
                            {value=6 : Yangi H-index}
                            {--profile=research_gate : scopus, web_of_science yoki research_gate}
                            {--datum= : Aniq datum ID, bir nechta mos resurs bo‘lsa majburiy}
                            {--actor= : Audit uchun administrator HEMIS ID si}
                            {--apply : O‘zgarishni bazaga yozish va report ballarini qayta hisoblash}';

    /** @var string */
    protected $description = 'H-index profil qiymatini tekshirib tuzatadi va tegishli report ballarini qayta hisoblaydi';

    public function handle(CorrectHIndexProfileValue $action): int
    {
        $hemisId = $this->positiveInteger('hemis_id');
        $expectedValue = $this->nonNegativeInteger('expected');
        $newValue = $this->nonNegativeInteger('value');
        $datumId = $this->nullablePositiveIntegerOption('datum');
        $profile = (string) $this->option('profile');
        $apply = (bool) $this->option('apply');

        if ($hemisId === null || $expectedValue === null || $newValue === null || $datumId === false) {
            $this->error('HEMIS ID va datum ID musbat, H-index qiymatlari manfiy bo‘lmagan butun son bo‘lishi kerak.');

            return self::FAILURE;
        }

        if (! array_key_exists($profile, CorrectHIndexProfileValue::PROFILES)) {
            $this->error('Profil scopus, web_of_science yoki research_gate bo‘lishi kerak.');

            return self::FAILURE;
        }

        $actor = null;
        if ($apply) {
            $actorHemisId = filter_var($this->option('actor'), FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]);
            $actor = $actorHemisId === false
                ? null
                : User::query()->where('hemis_id', $actorHemisId)->first();

            if ($actor === null || ! $actor->isSuperAdmin()) {
                $this->error('--apply uchun mavjud super administratorning --actor HEMIS ID si kerak.');

                return self::FAILURE;
            }
        }

        try {
            $result = $action->handle(
                $hemisId,
                $profile,
                $expectedValue,
                $newValue,
                $actor,
                $apply,
                $datumId === false ? null : $datumId,
            );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Holat', 'Datum', 'Report', 'Profil', 'H-index', 'Ball'],
            [[
                $apply ? ($result['changed'] ? 'APPLIED' : 'NO CHANGE') : 'DRY RUN',
                $result['datum_id'],
                $result['report_id'],
                CorrectHIndexProfileValue::PROFILES[$profile],
                $result['old_value'].' → '.$result['new_value'],
                number_format($result['old_point'], 2, '.', '').' → '
                    .number_format($result['new_point'], 2, '.', ''),
            ]],
        );

        if (! $apply) {
            $this->warn('Bazaga yozilmadi. Tekshiruv to‘g‘ri bo‘lsa --actor=<HEMIS_ID> --apply bilan ishga tushiring.');
        }

        return self::SUCCESS;
    }

    private function positiveInteger(string $argument): ?int
    {
        $value = filter_var($this->argument($argument), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        return $value === false ? null : $value;
    }

    private function nonNegativeInteger(string $argument): ?int
    {
        $value = filter_var($this->argument($argument), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0],
        ]);

        return $value === false ? null : $value;
    }

    private function nullablePositiveIntegerOption(string $option): int|false|null
    {
        if ($this->option($option) === null) {
            return null;
        }

        return filter_var($this->option($option), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
    }
}
