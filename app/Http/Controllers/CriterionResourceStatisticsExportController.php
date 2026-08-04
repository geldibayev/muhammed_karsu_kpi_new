<?php

namespace App\Http\Controllers;

use App\Actions\ExportCriterionResourceStatisticsToXlsx;
use App\Http\Requests\CriterionResourceStatisticsRequest;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CriterionResourceStatisticsExportController extends Controller
{
    public function __invoke(
        CriterionResourceStatisticsRequest $request,
        ExportCriterionResourceStatisticsToXlsx $exportCriterionResourceStatisticsToXlsx,
    ): BinaryFileResponse {
        Gate::authorize('view-resource-statistics');

        return $exportCriterionResourceStatisticsToXlsx->handle($request->validated());
    }
}
