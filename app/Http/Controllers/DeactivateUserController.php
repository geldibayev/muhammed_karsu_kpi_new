<?php

namespace App\Http\Controllers;

use App\Actions\DeactivateUser;
use App\Http\Requests\DeactivateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class DeactivateUserController extends Controller
{
    public function __invoke(
        DeactivateUserRequest $request,
        User $user,
        DeactivateUser $deactivateUser,
    ): RedirectResponse {
        $deactivateUser->handle($request->user(), $user);

        return back()->with(
            'success',
            $user->short.' faolsizlantirildi va barcha reytinglardan chiqarildi.',
        );
    }
}
