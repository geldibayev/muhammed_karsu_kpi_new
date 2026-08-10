<?php

namespace App\Http\Controllers;

use App\Actions\SyncHemisWorkplacesForLogin;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncHemisUserController extends Controller
{
    public function __invoke(User $user, SyncHemisWorkplacesForLogin $syncHemisWorkplaces): RedirectResponse
    {
        $this->authorize('syncHemis', $user);

        try {
            $syncedUser = $syncHemisWorkplaces->handle($user);
        } catch (Throwable $exception) {
            Log::error('Manual HEMIS user sync failed.', [
                'user_id' => $user->getKey(),
                'hemis_id' => $user->hemis_id,
                'exception' => $exception,
            ]);

            return back()->with('error', 'HEMIS ma’lumotlarini yangilab bo‘lmadi. Keyinroq qayta urinib ko‘ring.');
        }

        return back()->with(
            'success',
            "HEMIS javobi olindi: {$syncedUser->short}ning ish joyi, lavozimi va baholash toifasi yangilandi.",
        );
    }
}
