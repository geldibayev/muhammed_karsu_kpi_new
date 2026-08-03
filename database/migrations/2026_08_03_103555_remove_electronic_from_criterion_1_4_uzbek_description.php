<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const OLD_DESCRIPTION = 'Belgilangan tartib va talablar asosida elektron darslik va o‘quv qo‘llanmalarni boshqa tillardan tarjima qilinganligi tayyorlanib chop qilinganligi hamda ushbu o‘quv adabiyoti bo‘yicha universitetning nashr ruxsatnomasi, ISBN raqami asosida aniqlanadi. Mualliflik ulushi inobatga olinadi.';

    private const NEW_DESCRIPTION = 'Belgilangan tartib va talablar asosida darslik va o‘quv qo‘llanmalarni boshqa tillardan tarjima qilinganligi tayyorlanib chop qilinganligi hamda ushbu o‘quv adabiyoti bo‘yicha universitetning nashr ruxsatnomasi, ISBN raqami asosida aniqlanadi. Mualliflik ulushi inobatga olinadi.';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->replaceUzbekDescription(self::OLD_DESCRIPTION, self::NEW_DESCRIPTION);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->replaceUzbekDescription(self::NEW_DESCRIPTION, self::OLD_DESCRIPTION);
    }

    private function replaceUzbekDescription(string $currentDescription, string $replacementDescription): void
    {
        DB::transaction(function () use ($currentDescription, $replacementDescription): void {
            $criteria = DB::table('criteria')
                ->where('code', '1.4')
                ->lockForUpdate()
                ->get(['id', 'desc']);

            foreach ($criteria as $criterion) {
                $description = json_decode($criterion->desc, true, 512, JSON_THROW_ON_ERROR);

                if (($description['uz'] ?? null) !== $currentDescription) {
                    continue;
                }

                $description['uz'] = $replacementDescription;

                DB::table('criteria')
                    ->where('id', $criterion->id)
                    ->update([
                        'desc' => json_encode($description, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                        'updated_at' => now(),
                    ]);
            }
        }, 3);
    }
};
