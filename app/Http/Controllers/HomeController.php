<?php

namespace App\Http\Controllers;

use App\Models\Criterion;
use App\Models\Workplace;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $rate = auth()->user()->degree;
        $criteria = Criterion::whereNull('parent_id')
            ->whereHas('children.criterionEvaluations', function ($query) use ($rate) {
                $query->where('evaluation', $rate);
            })->with(['children' => function ($query) use ($rate) {
                $query->whereHas('criterionEvaluations', function ($q) use ($rate) {
                    $q->where('evaluation', $rate);
                });
            }])->get();
        $breadcrumbs = [
            [
                'url' => '#',
                'name' => 'Asosiy sahifa'
            ]
        ];
        return view('home', compact(['criteria', 'breadcrumbs']));
    }

    public function profile()
    {
        $breadcrumbs = [
            [
                'url' => '#',
                'name' => 'Asosiy sahifa'
            ]
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
