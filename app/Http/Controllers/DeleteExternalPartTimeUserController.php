<?php

namespace App\Http\Controllers;

use App\Actions\DeactivateUser;
use App\Http\Requests\DeleteExternalPartTimeUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class DeleteExternalPartTimeUserController extends Controller
{
    public function __invoke(
        DeleteExternalPartTimeUserRequest $request,
        User $user,
        DeactivateUser $deactivateUser,
    ): RedirectResponse {
        $deactivateUser->handle($request->user(), $user, deleteStoredFiles: true);

        return to_route('users.external-part-timers.index')
            ->with('success', 'Tashqi o‘rindosh o‘chirildi, kirishi bloklandi va ballari reytingdan chiqarildi.');
    }
}
