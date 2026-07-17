<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserRoleController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeSuperAdmin($request);

        $users = User::query()
            ->with('workplaces.department')
            ->orderBy('name->full')
            ->paginate(25);

        $breadcrumbs = [
            ['url' => route('home'), 'name' => 'Asosiy sahifa'],
            ['url' => '#', 'name' => 'Foydalanuvchi rollari'],
        ];

        return view('pages.users.roles.index', [
            'users' => $users,
            'roles' => User::ASSIGNABLE_ROLES,
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);

        abort_if($user->isSuperAdmin(), 403, 'Super admin roli o‘zgartirilmaydi.');

        $validated = $request->validate([
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'distinct', 'in:'.implode(',', array_keys(User::ASSIGNABLE_ROLES))],
        ]);

        $roles = array_values($validated['roles'] ?? []);

        if ($roles === []) {
            $roles = ['teacher'];
        }

        $user->update(['rol' => $roles]);

        return back()->with('success', $user->short.' uchun rollar saqlandi.');
    }

    private function authorizeSuperAdmin(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
    }
}
