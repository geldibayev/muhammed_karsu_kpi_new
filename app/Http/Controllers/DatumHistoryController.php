<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DatumHistoryController extends Controller
{
    public function show($status)
    {
        return redirect()->back()->with('error', 'Sahifa ishlab chiqilmoqda');
        //dd($status);
    }
}
