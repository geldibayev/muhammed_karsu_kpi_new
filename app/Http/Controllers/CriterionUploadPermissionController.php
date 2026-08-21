<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCriterionUploadPermissionRequest;
use App\Models\CriterionUploadPermission;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class CriterionUploadPermissionController extends Controller
{
    public function store(StoreCriterionUploadPermissionRequest $request): RedirectResponse
    {
        $user = User::query()->findOrFail($request->integer('user_id'));
        $permission = CriterionUploadPermission::query()->create([
            'user_id' => $user->getKey(),
            'criterion_id' => $request->integer('criterion_id'),
            'granted_by_user_id' => $request->user()->getKey(),
            'reason' => $request->validated('reason'),
        ]);

        Log::info('Criterion-specific resource upload permission granted.', [
            'permission_id' => $permission->getKey(),
            'user_id' => $user->getKey(),
            'hemis_id' => $user->hemis_id,
            'criterion_id' => $permission->criterion_id,
            'granted_by_user_id' => $request->user()->getKey(),
            'reason' => $permission->reason,
        ]);

        return back()->with('success', 'Foydalanuvchiga tanlangan kriteriya uchun yuklash ruxsati berildi.');
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
