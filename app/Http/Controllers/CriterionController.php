<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCriterionRequest;
use App\Models\Criterion;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CriterionController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->to('/login');
    }

    public function edit(Criterion $criterion): View
    {
        $this->authorize('update', $criterion);

        return view('pages.admin.criteria.edit', compact(['criterion']));
    }

    public function update(UpdateCriterionRequest $request, Criterion $criterion): RedirectResponse
    {
        $criterion->update($request->validated());

        return redirect()->route('home');
    }
}
