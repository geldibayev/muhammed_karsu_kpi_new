<?php

namespace App\Http\Controllers;

use App\Models\Criterion;
use App\Models\Department;
use App\Models\Language;
use App\Models\Year;
use Illuminate\Http\Request;

class CriterionController extends Controller
{
    public function edit(Criterion $criterion)
    {
        return view('pages.admin.criteria.edit', compact(['criterion']));
    }

    public function update(Request $request, Criterion $criterion)
    {
        $cr = Criterion::find($criterion->id);
        //dd($cr);
        $cr->ai_prompt = $request->ai_prompt;
        $cr->save();
        return redirect()->route('home');
    }
}
