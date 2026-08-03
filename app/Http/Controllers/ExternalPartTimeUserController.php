<?php

namespace App\Http\Controllers;

use App\Actions\GetExternalPartTimeUsers;
use Illuminate\View\View;

class ExternalPartTimeUserController extends Controller
{
    public function index(GetExternalPartTimeUsers $getExternalPartTimeUsers): View
    {
        return view('pages.users.external-part-timers.index', [
            'users' => $getExternalPartTimeUsers->paginate(),
            'breadcrumbs' => [
                ['url' => route('home'), 'name' => 'Asosiy sahifa'],
                ['url' => '#', 'name' => 'Tashqi o‘rindoshlar'],
            ],
        ]);
    }
}
