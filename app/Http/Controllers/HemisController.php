<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;

class HemisController extends Controller
{
    public function index(Request $request)
    {
        $employeeProvider = new GenericProvider([
            'clientId' => env('HEMIS_CLIENT_ID'),
            'clientSecret' => env('HEMIS_CLIENT_SECRET'),
            'redirectUri' => env('REDIRECT_URL'),
            'urlAuthorize' => env('HEMIS_URL') . '/oauth/authorize',
            'urlAccessToken' => env('HEMIS_URL') . '/oauth/access-token',
            'urlResourceOwnerDetails' => env('HEMIS_URL') . '/oauth/api/user?fields=id,uuid,employee_id_number,type,roles,name,login,email,picture,firstname,surname,patronymic,birth_date,university_id,phone'
        ]);

        if (!$request->has('code')) {
            $authorizationUrl = $employeeProvider->getAuthorizationUrl();

            // 1. Eski state'ni tozalaymiz (xatoliklarni oldini olish uchun)
            $request->session()->forget('oauth2state');

            // 2. Yangi state'ni yozamiz
            $request->session()->put('oauth2state', $employeeProvider->getState());

            // 3. MUHIM: Boshqa sahifaga o'tib ketishdan oldin sessiyani majburiy saqlaymiz!
            $request->session()->save();

            return redirect()->away($authorizationUrl);
        }

        $state = $request->input('state');
        $sessionState = $request->session()->pull('oauth2state');

        // Qolgan tekshiruvlar...
        if (empty($state) || $state !== $sessionState) {
            return redirect(route('home'))->with('error', 'Yaroqsiz so\'rov holati (Invalid state). Iltimos qaytadan kiring.');
        }

        try {
            $accessToken = $employeeProvider->getAccessToken('authorization_code', [
                'code' => $request->input('code')
            ]);
            $resourceOwner = $employeeProvider->getResourceOwner($accessToken);
            $userArray = $resourceOwner->toArray();

            $userData = [
                'hemis_id' => $userArray['employee_id_number'],
                'name' => json_encode([
                    'full' => $userArray['name'] ?? '',
                    'first' => $userArray['firstname'] ?? '',
                    'last' => $userArray['surname'] ?? '',
                    'third' => $userArray['patronymic'] ?? '',
                    'short' => User::make_short_name($userArray['firstname'] ?? '', $userArray['surname'] ?? '', $userArray['patronymic'] ?? ''),
                ]),
                'image' => json_encode([
                    'min' => $userArray['picture'] ?? null,
                    'max' => $userArray['picture_full'] ?? null,
                ]),
                'user_data' => json_encode($userArray),
            ];

            $user = User::find($userArray['employee_id']);

            if ($user) {
                $user->update($userData);
            } else {
                $isSuperAdmin = ($userArray['employee_id'] == 1568);
                $userData['id'] = $userArray['employee_id'];
                $userData['pos'] = 'user';
                $userData['rol'] = $isSuperAdmin ? ['super_admin', 'user'] : ['user'];
                User::create($userData);
                $user = User::find($userArray['employee_id']);
            }
            Auth::login($user);
            $request->session()->regenerate();
            return redirect(route('home'))->with('success', 'Tizimga muvaffaqiyatli kirdingiz! Vaqt: ' . date('d.m.Y H:i:s'));

        } catch (IdentityProviderException $e) {
            Log::error('HEMIS OAuth Xatoligi: ' . $e->getMessage());
            return redirect(route('home'))->with('error', 'HEMIS orqali kirishda xatolik yuz berdi. Keyinroq qayta urinib ko\'ring.');
        }
    }
}
