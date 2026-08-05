<?php

namespace App\Http\Controllers;

use App\Actions\ChangeEducationalContentType;
use App\Http\Requests\ChangeEducationalContentTypeRequest;
use App\Models\Datum;
use Illuminate\Http\RedirectResponse;

class ChangeEducationalContentTypeController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        ChangeEducationalContentTypeRequest $request,
        Datum $datum,
        ChangeEducationalContentType $action,
    ): RedirectResponse {
        $action->handle($request->user(), $datum, $request->integer('score_option_id'));

        return redirect()
            ->route('upload.details', $datum)
            ->with('success', '1.1 resurs turi o‘zgartirildi va ball qayta hisoblandi.');
    }
}
