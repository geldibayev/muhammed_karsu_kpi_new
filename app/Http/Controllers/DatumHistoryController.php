<?php

namespace App\Http\Controllers;

use App\Enums\DatumStatus;
use App\Models\Datum;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DatumHistoryController extends Controller
{
    public function index(Request $request, DatumStatus $status): View
    {
        $this->authorize('viewAny', Datum::class);

        $breadcrumbs = [
            [
                'url' => route('home'),
                'name' => 'Asosiy sahifa',
            ],
            [
                'url' => '#',
                'name' => $status->label().' resurslar',
            ],
        ];

        $data = Datum::query()
            ->with([
                'criterion:id,name',
                'year:id,name',
            ])
            ->whereBelongsTo($request->user())
            ->where('status', $status->value)
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('pages.users.data', compact('data', 'breadcrumbs', 'status'));
    }
}
