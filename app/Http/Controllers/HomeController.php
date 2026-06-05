<?php

namespace App\Http\Controllers;

use App\Models\Criterion;
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

    public function logout()
    {
        auth()->logout();
        return redirect()->route('login');
    }
}
