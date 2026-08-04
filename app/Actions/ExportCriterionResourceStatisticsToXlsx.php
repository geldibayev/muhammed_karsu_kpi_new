<?php

namespace App\Actions;

use App\Models\Criterion;
use App\Support\XlsxWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportCriterionResourceStatisticsToXlsx
{
    public function __construct(
        private GetCriterionResourceStatisticsTable $getCriterionResourceStatisticsTable,
        private XlsxWriter $xlsxWriter,
    ) {}

    /** @param array{sort?: string, direction?: string} $filters */
    public function handle(array $filters): BinaryFileResponse
    {
        $rows = $this->getCriterionResourceStatisticsTable
            ->handle($filters)
            ->values()
            ->map(fn (Criterion $criterion, int $index): array => [
                $index + 1,
                $criterion->code ?: '—',
                (string) data_get($criterion->parent?->name, 'uz', 'Nomsiz bo‘lim'),
                (string) data_get($criterion->name, 'uz', 'Nomsiz kriteriya'),
                $this->responsibleName($criterion),
                (int) $criterion->total,
                (int) $criterion->checked,
                (int) $criterion->unchecked,
                (int) $criterion->returned,
                (int) $criterion->deleted,
                (int) $criterion->other,
            ]);

        $path = $this->xlsxWriter->write('Kriteriyalar statistikasi', [
            'T/r',
            'Kod',
            'Bo‘lim',
            'Kriteriya',
            'Mas’ul',
            'Jami',
            'Tekshirilgan',
            'Tekshirilmagan',
            'Qaytarilgan',
            'O‘chirilgan',
            'Boshqa',
        ], $rows);

        return response()
            ->download(
                $path,
                'kriteriyalar-statistikasi-'.now()->format('Y-m-d-His').'.xlsx',
                ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            )
            ->deleteFileAfterSend(true);
    }

    private function responsibleName(Criterion $criterion): string
    {
        if ($criterion->checking === 'ai') {
            return 'Sun’iy intellekt';
        }

        $assignment = $criterion->reviewerAssignment;

        if ($assignment === null) {
            return '—';
        }

        return $assignment->user?->full
            ?: ($assignment->user?->short ?: 'HEMIS ID: '.$assignment->hemis_id);
    }
}
