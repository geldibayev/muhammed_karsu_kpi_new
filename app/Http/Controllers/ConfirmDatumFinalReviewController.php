<?php

namespace App\Http\Controllers;

use App\Actions\ConfirmDatumFinalReview;
use App\Models\Datum;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ConfirmDatumFinalReviewController extends Controller
{
    public function __invoke(
        Request $request,
        Datum $datum,
        ConfirmDatumFinalReview $action,
    ): RedirectResponse {
        $action->handle($request->user(), $datum);

        return redirect()
            ->route('upload.details', $datum)
            ->with('success', 'Resursning yakuniy tekshiruvi tasdiqlandi.');
    }
}
