<?php

namespace App\Console\Commands;

use App\Models\AiHumanReviewAssignment;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SetAiHumanReviewer extends Command
{
    protected $signature = 'kpi:ai:set-human-reviewer
                            {hemis_id : AI inson tekshiruvchisi foydalanuvchisining HEMIS IDsi}
                            {--dry-run : Ma’lumotni o‘zgartirmasdan natijani ko‘rsatish}';

    protected $description = 'Barcha AI mezonlari uchun yagona inson tekshiruvchisini sozlaydi';

    public function handle(): int
    {
        $hemisIdInput = (string) $this->argument('hemis_id');

        if (! ctype_digit($hemisIdInput) || (int) $hemisIdInput <= 0) {
            $this->error('HEMIS ID musbat butun son bo‘lishi kerak.');

            return self::FAILURE;
        }

        $hemisId = (int) $hemisIdInput;
        $reviewer = User::query()
            ->select(['id', 'name', 'hemis_id'])
            ->where('hemis_id', $hemisId)
            ->first();

        if ($reviewer === null) {
            $this->error("HEMIS ID {$hemisId} bo‘lgan foydalanuvchi topilmadi.");

            return self::FAILURE;
        }

        $currentHemisId = AiHumanReviewAssignment::activeHemisId();

        if ((bool) $this->option('dry-run')) {
            $this->info($currentHemisId === $hemisId
                ? "AI inson tekshiruvchisi allaqachon HEMIS ID {$hemisId}."
                : "AI inson tekshiruvchisi HEMIS ID {$hemisId} ga o‘zgartiriladi.");

            return self::SUCCESS;
        }

        $changed = DB::transaction(function () use ($hemisId): bool {
            $currentAssignment = AiHumanReviewAssignment::query()
                ->active()
                ->lockForUpdate()
                ->first();

            if ((int) $currentAssignment?->hemis_id === $hemisId) {
                return false;
            }

            $now = now();
            $currentAssignment?->update([
                'active_slot' => null,
                'ended_at' => $now,
            ]);
            AiHumanReviewAssignment::query()->create([
                'hemis_id' => $hemisId,
                'active_slot' => 1,
                'assigned_at' => $now,
            ]);

            return true;
        }, 3);

        $this->info($changed
            ? "AI inson tekshiruvchisi HEMIS ID {$hemisId} ga o‘zgartirildi."
            : "AI inson tekshiruvchisi allaqachon HEMIS ID {$hemisId}.");

        return self::SUCCESS;
    }
}
