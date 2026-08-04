<?php

namespace App\Http\Controllers;

use App\Actions\RejectAcceptedAiDatum;
use App\Http\Requests\RejectAcceptedAiDatumRequest;
use App\Models\Datum;
use Illuminate\Http\RedirectResponse;

class RejectAcceptedAiDatumController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        RejectAcceptedAiDatumRequest $request,
        Datum $datum,
        RejectAcceptedAiDatum $action,
    ): RedirectResponse {
        $action->handle($request->user(), $datum, $request->validated('reason'));

        return redirect()
            ->route('upload.details', $datum)
            ->with('success', 'Gemini tasdiqlagan resurs izoh bilan rad etildi.');
    }
}
