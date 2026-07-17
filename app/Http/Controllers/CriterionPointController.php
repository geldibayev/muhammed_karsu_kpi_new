<?php

namespace App\Http\Controllers;

use App\Actions\RecalculateReportPoints;
use App\Models\Report;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class CriterionPointController extends Controller
{
    public function rebuild(Report $report, RecalculateReportPoints $recalculateReportPoints): RedirectResponse
    {
        Gate::authorize('rebuild-report-points');

        $recalculateReportPoints->handle($report);

        return back()->with('success', 'Hisobot ballari muvaffaqiyatli qayta hisoblandi.');
    }
}
