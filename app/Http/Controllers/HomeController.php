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
        $rate =auth()->user()->degree;
        $criteria = Criterion::whereNull('parent_id')->get();
        return view('home', compact(['criteria']));
    }
}
