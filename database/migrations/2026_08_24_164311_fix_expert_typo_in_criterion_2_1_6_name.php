<?php

use App\Support\InternationalCooperationCriterionRule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const OLD_FRAGMENT = '(yekspert, mutaxassis)';

    private const NEW_FRAGMENT = '(ekspert, mutaxassis)';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->replaceUzbekNameFragment(self::OLD_FRAGMENT, self::NEW_FRAGMENT);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->replaceUzbekNameFragment(self::NEW_FRAGMENT, self::OLD_FRAGMENT);
    }

    private function replaceUzbekNameFragment(string $currentFragment, string $replacementFragment): void
    {
        DB::transaction(function () use ($currentFragment, $replacementFragment): void {
            $criteria = DB::table('criteria')
                ->where('code', InternationalCooperationCriterionRule::CODE)
                ->lockForUpdate()
                ->get(['id', 'name']);

            foreach ($criteria as $criterion) {
                $name = json_decode($criterion->name, true, 512, JSON_THROW_ON_ERROR);
                $uzbekName = (string) ($name['uz'] ?? '');
                $updatedUzbekName = str_replace($currentFragment, $replacementFragment, $uzbekName);

                if ($updatedUzbekName === $uzbekName) {
                    continue;
                }

                $name['uz'] = $updatedUzbekName;

                DB::table('criteria')
                    ->where('id', $criterion->id)
                    ->update([
                        'name' => json_encode($name, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                        'updated_at' => now(),
                    ]);
            }
        }, 3);
    }
};
