<?php

namespace App\Http\Controllers;

use App\Models\Datum;
use Illuminate\Http\Request;

class DatumHistoryController extends Controller
{
    public function show($status)
    {
        $breadcrumb_text = 'Yangi resurslar';
        if ($status == 'checking') $breadcrumb_text = 'Tekshirilmoqda';
        if ($status == 'accepted') $breadcrumb_text = 'Tasdiqlangan';
        if ($status == 'cancelled') $breadcrumb_text = 'Bekor qilingan';
        $breadcrumbs = [
            [
                'url' => route('home'),
                'name' => 'Asosiy sahifa'
            ],
            [
                'url' => '#',
                'name' => $breadcrumb_text
            ]
        ];
        $data = Datum::where('status', $status)->where('user_id', auth()->id())->paginate(20);
        return view('pages.users.data', compact(['data', 'breadcrumbs', 'status']));
    }
}
