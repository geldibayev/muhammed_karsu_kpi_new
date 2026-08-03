<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const OLD_TITLE = 'Elektron darslik va o‘quv qo‘llanma yaratish yoki boshqa tillarda tarjima qilganligi';

    private const NEW_TITLE = 'Darsliklik va o‘quv qo‘llanmalarni boshqa tillardan tarjima qilganligi';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->replaceUzbekTitle(self::OLD_TITLE, self::NEW_TITLE);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->replaceUzbekTitle(self::NEW_TITLE, self::OLD_TITLE);
    }

    private function replaceUzbekTitle(string $currentTitle, string $replacementTitle): void
    {
        DB::transaction(function () use ($currentTitle, $replacementTitle): void {
            $criteria = DB::table('criteria')
                ->where('code', '1.4')
                ->lockForUpdate()
                ->get(['id', 'name']);

            foreach ($criteria as $criterion) {
                $name = json_decode($criterion->name, true, 512, JSON_THROW_ON_ERROR);

                if (($name['uz'] ?? null) !== $currentTitle) {
                    continue;
                }

                $name['uz'] = $replacementTitle;

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
