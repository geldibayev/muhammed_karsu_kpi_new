<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserRoleController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeSuperAdmin($request);

        $search = $request->string('search')->trim()->toString();
        $users = User::query()
            ->select(['id', 'hemis_id', 'name', 'rol', 'status'])
            ->with('ratingWorkplace.department')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('name->full', 'like', "%{$search}%")
                        ->orWhere('name->short', 'like', "%{$search}%");

                    if (ctype_digit($search)) {
                        $query->orWhere('hemis_id', (int) $search);
                    }
                });
            })
            ->orderBy('name->full')
            ->paginate(25)
            ->withQueryString();

        $breadcrumbs = [
            ['url' => route('home'), 'name' => 'Asosiy sahifa'],
            ['url' => '#', 'name' => 'Foydalanuvchilar'],
        ];

        return view('pages.users.roles.index', [
            'users' => $users,
            'search' => $search,
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    private function authorizeSuperAdmin(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
    }
}
