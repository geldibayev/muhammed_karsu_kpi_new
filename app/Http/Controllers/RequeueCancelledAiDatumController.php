<?php

namespace App\Http\Controllers;

use App\Actions\RequeueCancelledAiDatum;
use App\Models\Datum;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RequeueCancelledAiDatumController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        Request $request,
        Datum $datum,
        RequeueCancelledAiDatum $requeueCancelledAiDatum,
    ): RedirectResponse {
        $this->authorize('requeueAiEvaluation', $datum);

        $requeueCancelledAiDatum->handle($request->user(), $datum);

        return redirect()
            ->route('upload.details', $datum)
            ->with('success', 'Resurs AI tekshiruviga qayta yuborildi.');
    }
}
