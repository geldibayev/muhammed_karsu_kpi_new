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
        $rate = 'no_degrees';
        $criteria = Criterion::whereNull('parent_id')
            ->with(['children' => function ($query) use ($rate) {
                $query->whereHas('criterionEvaluation', function ($q) use ($rate) {
                    $q->where('evaluation', $rate);
                })->with(['criterionEvaluation' => function ($q) use ($rate) {
                    $q->where('evaluation', $rate)
                        ->select('id', 'criterion_id', 'evaluation', 'score');
                }]);
            }])->get();
        return view('home', compact(['criteria']));
    }
}
