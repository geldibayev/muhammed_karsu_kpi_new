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

        $query = Datum::query()->where('status', $status->value);

        if (! $request->user()->isSuperAdmin() || $status !== DatumStatus::Cancelled) {
            $query->whereBelongsTo($request->user());
        }

        $totalPoints = $status === DatumStatus::Accepted
            ? (clone $query)->sum('point')
            : null;

        $relations = [
            'criterion:id,name,checking',
            'duplicateOf:id,name,status',
            'year:id,name',
        ];

        if ($request->user()->isSuperAdmin() && $status === DatumStatus::Cancelled) {
            $relations[] = 'user:id,hemis_id,name';
            $relations[] = 'histories:id,datum_id,message_type';
        }

        $data = $query
            ->with($relations)
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('pages.users.data', compact('data', 'breadcrumbs', 'status', 'totalPoints'));
    }
}
