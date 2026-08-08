<?php

namespace App\Console\Commands;

use App\Actions\ImportDisciplinarySanctions;
use Illuminate\Console\Command;
use Throwable;

class ImportDisciplinarySanctionsCommand extends Command
{
    /** @var string */
    protected $signature = 'kpi:discipline:import
                            {--file= : Ixtiyoriy: private storage ichidagi XLSX fayl nomi}
                            {--apply : Ro‘yxatni saqlash, 4.1.6 ballarini yozish va reportni qayta hisoblash}';

    /** @var string */
    protected $description = 'Koddagi yoki XLSX dagi intizomiy jazo ro‘yxatini import qiladi va 4.1.6 ballarini hisoblaydi';

    public function handle(ImportDisciplinarySanctions $action): int
    {
        $filename = (string) $this->option('file');
        $apply = (bool) $this->option('apply');

        try {
            $result = $filename === ''
                ? $action->handleBuiltIn($apply)
                : $action->handle($filename, $apply);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Holat', 'Ro‘yxat', 'Tizimda bor', 'Baholangan users', 'O‘zgargan ballar', 'Snapshot'],
            [[
                $apply ? 'APPLIED' : 'DRY RUN',
                $result['rows'],
                $result['existing_users'],
                $result['scored_users'],
                $result['changed_scores'],
                $result['changed_snapshot'] ? 'yangi' : 'o‘zgarmagan',
            ]],
        );

        if (! $apply) {
            $this->warn('Bazaga yozilmadi. Natija to‘g‘ri bo‘lsa --apply bilan ishga tushiring.');
        }

        return self::SUCCESS;
    }
}
