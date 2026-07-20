<?php

namespace App\Http\Controllers\Auth;

use App\Actions\GetRatingIndexData;
use App\Http\Controllers\Controller;
use App\Http\Requests\RatingFilterRequest;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\View\View;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/home';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }

    public function showLoginForm(
        RatingFilterRequest $request,
        GetRatingIndexData $getRatingIndexData,
    ): View {
        return view('auth.login', $getRatingIndexData->handle($request->validated()));
    }
}
