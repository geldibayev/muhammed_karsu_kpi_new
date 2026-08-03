<?php

namespace App\Actions;

use App\Support\XlsxWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportExternalPartTimeUsersToXlsx
{
    public function __construct(
        private XlsxWriter $xlsxWriter,
        private GetExternalPartTimeUsers $getExternalPartTimeUsers,
    ) {}

    public function handle(): BinaryFileResponse
    {
        $rows = $this->getExternalPartTimeUsers
            ->all()
            ->values()
            ->map(fn (array $user, int $index): array => [
                $index + 1,
                (string) $user['hemis_id'],
                $user['name'],
                $user['faculties'],
                $user['departments'],
                $user['positions'],
                $user['forms'],
            ]);

        $path = $this->xlsxWriter->write('Tashqi o‘rindoshlar', [
            'T/r',
            'HEMIS ID',
            'F.I.Sh.',
            'Fakultet',
            'Kafedra',
            'Lavozim',
            'Mehnat shakli',
        ], $rows);

        return response()
            ->download(
                $path,
                'tashqi-orindoshlar-'.now()->format('Y-m-d-His').'.xlsx',
                ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            )
            ->deleteFileAfterSend(true);
    }
}
