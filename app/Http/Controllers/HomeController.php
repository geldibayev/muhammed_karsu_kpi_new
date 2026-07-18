<?php

namespace App\Http\Controllers;

use App\Models\Criterion;
use App\Models\Workplace;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request): View
    {
        $degree = $request->user()->degree;
        $criteria = Criterion::query()
            ->whereNull('parent_id')
            ->with([
                'children.criterionEvaluations' => fn (HasMany $query): HasMany => $query
                    ->where('evaluation', $degree)
                    ->where('has', '1'),
            ])
            ->get();
        $breadcrumbs = [
            [
                'url' => '#',
                'name' => 'Asosiy sahifa',
            ],
        ];

        return view('home', compact(['criteria', 'breadcrumbs']));
    }

    public function profile()
    {
        $breadcrumbs = [
            [
                'url' => '#',
                'name' => 'Asosiy sahifa',
            ],
        ];

        $user = auth()->user()->load([
            'workplaces.department',
            'workplaces.staff',
            'workplaces.form',
            'workplaces.position',
            'workplaces.status',
        ]);

        $workpl = Workplace::where('user_id', auth()->id())->first();

        return view('pages.users.profile', compact(['breadcrumbs', 'workpl', 'user']));
    }

    public function logout()
    {
        auth()->logout();

        return redirect()->route('login');
    }
}
