<?php

namespace App\Http\Controllers;

use App\Actions\ApproveCancelledAiDatum;
use App\Http\Requests\ApproveCancelledAiDatumRequest;
use App\Models\Datum;
use Illuminate\Http\RedirectResponse;

class ApproveCancelledAiDatumController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        ApproveCancelledAiDatumRequest $request,
        Datum $datum,
        ApproveCancelledAiDatum $action,
    ): RedirectResponse {
        $action->handle(
            $request->user(),
            $datum,
            $request->filled('point') ? $request->float('point') : null,
            $request->filled('score_option_id') ? $request->integer('score_option_id') : null,
            $request->filled('publication_tier') ? $request->string('publication_tier')->toString() : null,
            $request->filled('author_count') ? $request->integer('author_count') : null,
            $request->filled('received_amount') ? $request->float('received_amount') : null,
        );

        return redirect()
            ->route('upload.details', $datum)
            ->with('success', 'Rad etilgan resurs tasdiqlandi va ball hisoblandi.');
    }
}
