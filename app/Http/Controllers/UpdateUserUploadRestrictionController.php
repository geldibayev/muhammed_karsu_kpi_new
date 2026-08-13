<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateUserUploadRestrictionRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class UpdateUserUploadRestrictionController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(UpdateUserUploadRestrictionRequest $request, User $user): RedirectResponse
    {
        $blocked = $request->boolean('blocked');

        $user->update([
            'upload_blocked_at' => $blocked ? now() : null,
            'upload_block_reason' => $blocked ? $request->validated('reason') : null,
            'upload_blocked_by_user_id' => $blocked ? $request->user()->getKey() : null,
        ]);

        return back()->with(
            'success',
            $user->short.($blocked ? ' uchun resurs yuklash bloklandi.' : ' uchun resurs yuklash blokdan chiqarildi.'),
        );
    }
}
