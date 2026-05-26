<?php

namespace App\Http\Controllers;

use App\Models\AcademicDegree;
use App\Models\AcademicRank;
use App\Models\EmployeeStatus;
use App\Models\EmployeeType;
use App\Models\EmploymentForm;
use App\Models\EmploymentStaff;
use App\Models\StaffPosition;
use App\Models\User;
use App\Models\Workplace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
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
            $http = Http::withToken(env('HEMIS_API_KEY'))->get('https://student.karsu.uz/rest/v1/data/employee-list', [
                'type' => 'all',
                'search' => $userArray['employee_id_number'],
            ]);
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
            $httpGet = $http->json();
            foreach ($httpGet['data']['items'] as $value) {
                $academic_degree = AcademicDegree::firstOrCreate([
                    'id' => $value['academicDegree']['code'],
                ], [
                    'name' => $value['academicDegree']['name'],
                ]);
                $academic_rank = AcademicRank::firstOrCreate([
                    'id' => $value['academicRank']['code'],
                ], [
                    'name' => $value['academicRank']['name'],
                ]);
                $form = EmploymentForm::firstOrCreate([
                    'id' => $value['employmentForm']['code'],
                ], [
                    'name' => $value['employmentForm']['name'],
                ]);
                $staffX = EmploymentStaff::firstOrCreate([
                    'id' => $value['employmentStaff']['code'],
                ], [
                    'name' => $value['employmentStaff']['name'],
                ]);
                $staff_position = StaffPosition::firstOrCreate([
                    'id' => $value['staffPosition']['code'],
                ], [
                    'name' => $value['staffPosition']['name'],
                ]);
                $type = EmployeeType::firstOrCreate([
                    'id' => $value['employeeType']['code'],
                ], [
                    'name' => $value['employeeType']['name'],
                ]);
                $status = EmployeeStatus::firstOrCreate([
                    'id' => $value['employeeStatus']['code'],
                ], [
                    'name' => $value['employeeStatus']['name'],
                ]);
                Workplace::firstOrCreate([
                    'user_id' => $value['id'],
                    'department_id' => $value['department']['id'],
                    'academic_degree_id' => $academic_degree->id,
                    'academic_rank_id' => $academic_rank->id,
                    'form_id' => $form->id,
                    'staff_id' => $staffX->id,
                    'staff_position_id' => $staff_position->id,
                    'status_id' => $status->id,
                    'type_id' => $type->id,
                ]);

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
