<?php

namespace App\Http\Controllers;

use App\Actions\CorrectHIndexProfileValue;
use App\Http\Requests\UpdateHIndexProfileRequest;
use App\Models\Datum;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use UnexpectedValueException;

class UpdateHIndexProfileController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        UpdateHIndexProfileRequest $request,
        Datum $datum,
        CorrectHIndexProfileValue $action,
    ): RedirectResponse {
        try {
            $action->handle(
                (int) $datum->user()->value('hemis_id'),
                $request->validated('profile'),
                $request->integer('expected_value'),
                $request->integer('new_value'),
                $request->user(),
                true,
                $datum->getKey(),
            );
        } catch (UnexpectedValueException $exception) {
            throw ValidationException::withMessages(['new_value' => $exception->getMessage()]);
        }

        return redirect()
            ->route('upload.details', $datum)
            ->with('success', 'H-index qiymati va ball muvaffaqiyatli yangilandi.');
    }
}
