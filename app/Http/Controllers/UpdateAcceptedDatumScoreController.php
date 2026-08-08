<?php

namespace App\Http\Controllers;

use App\Actions\UpdateAcceptedDatumScore;
use App\Http\Requests\UpdateAcceptedDatumScoreRequest;
use App\Models\Datum;
use Illuminate\Http\RedirectResponse;

class UpdateAcceptedDatumScoreController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        UpdateAcceptedDatumScoreRequest $request,
        Datum $datum,
        UpdateAcceptedDatumScore $action,
    ): RedirectResponse {
        $action->handle(
            $request->user(),
            $datum,
            $request->float('point'),
            $request->validated('score_change_reason'),
        );

        return redirect()
            ->route('upload.details', $datum)
            ->with('success', 'Tasdiqlangan resurs balli o‘zgartirildi.');
    }
}
