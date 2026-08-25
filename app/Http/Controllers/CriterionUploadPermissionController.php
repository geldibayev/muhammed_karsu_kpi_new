<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCriterionUploadPermissionRequest;
use App\Models\CriterionUploadPermission;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class CriterionUploadPermissionController extends Controller
{
    public function store(StoreCriterionUploadPermissionRequest $request): RedirectResponse
    {
        $user = User::query()->findOrFail($request->integer('user_id'));
        $criterionIds = $request->criterionIdsForGrant($user);
        $grantedCount = DB::transaction(function () use ($request, $user, $criterionIds): int {
            $grantedCount = 0;

            foreach ($criterionIds as $criterionId) {
                $permission = CriterionUploadPermission::query()->firstOrCreate([
                    'user_id' => $user->getKey(),
                    'criterion_id' => $criterionId,
                    'active_key' => true,
                ], [
                    'granted_by_user_id' => $request->user()->getKey(),
                    'reason' => $request->validated('reason'),
                ]);

                if (! $permission->wasRecentlyCreated) {
                    continue;
                }

                $grantedCount++;
                Log::info('Criterion-specific resource upload permission granted.', [
                    'permission_id' => $permission->getKey(),
                    'user_id' => $user->getKey(),
                    'hemis_id' => $user->hemis_id,
                    'criterion_id' => $permission->criterion_id,
                    'granted_by_user_id' => $request->user()->getKey(),
                    'reason' => $permission->reason,
                ]);
            }

            return $grantedCount;
        }, 3);

        return back()->with(
            'success',
            $grantedCount > 0
                ? "Foydalanuvchiga {$grantedCount} ta kriteriya uchun yuklash ruxsati berildi."
                : 'Tanlangan kriteriyalar uchun faol ruxsatlar avval berilgan.',
        );
    }

    public function destroy(Request $request, CriterionUploadPermission $permission): RedirectResponse
    {
        Gate::authorize('manage-upload-permissions');
        abort_unless($permission->active_key, 404);
        $permission->update([
            'active_key' => null,
            'revoked_at' => now(),
            'revoked_by_user_id' => $request->user()->getKey(),
        ]);

        Log::info('Criterion-specific resource upload permission revoked.', [
            'permission_id' => $permission->getKey(),
            'user_id' => $permission->user_id,
            'criterion_id' => $permission->criterion_id,
            'revoked_by_user_id' => $request->user()->getKey(),
        ]);

        return back()->with('success', 'Maxsus yuklash ruxsati bekor qilindi.');
    }
}
