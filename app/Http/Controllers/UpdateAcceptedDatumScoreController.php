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
            $request->filled('point') ? $request->float('point') : null,
            $request->validated('score_change_reason'),
            $request->filled('publication_tier') ? $request->string('publication_tier')->toString() : null,
            $request->filled('author_count') ? $request->integer('author_count') : null,
            $request->filled('received_amount') ? $request->float('received_amount') : null,
        );

        return redirect()
            ->route('upload.details', $datum)
            ->with('success', 'Tasdiqlangan resurs balli o‘zgartirildi.');
    }
}
