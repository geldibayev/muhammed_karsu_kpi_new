<?php

namespace App\Http\Controllers;

use App\Actions\DeleteExternalPartTimeUser;
use App\Http\Requests\DeleteExternalPartTimeUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class DeleteExternalPartTimeUserController extends Controller
{
    public function __invoke(
        DeleteExternalPartTimeUserRequest $request,
        User $user,
        DeleteExternalPartTimeUser $deleteExternalPartTimeUser,
    ): RedirectResponse {
        $deleteExternalPartTimeUser->handle($request->user(), $user);

        return to_route('users.external-part-timers.index')
            ->with('success', 'Tashqi o‘rindosh o‘chirildi, kirishi bloklandi va ballari reytingdan chiqarildi.');
    }
}
