<?php

namespace App\Http\Controllers;

use App\Models\AcademicDegree;
use App\Models\AcademicRank;
use App\Models\Department;
use App\Models\EmployeeStatus;
use App\Models\EmployeeType;
use App\Models\EmploymentForm;
use App\Models\EmploymentStaff;
use App\Models\StaffPosition;
use App\Models\User;
use App\Models\Workplace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use League\OAuth2\Client\Provider\GenericProvider;
use Throwable;

class HemisController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        $provider = $this->provider();

        if (! $request->filled('code')) {
            if ($request->filled('error')) {
                return to_route('home')->with('error', 'HEMIS tizimiga kirish bekor qilindi yoki rad etildi.');
            }

            return $this->redirectToHemis($request, $provider);
        }

        if (! $this->hasValidState($request)) {
            return to_route('home')->with('error', 'Yaroqsiz so‘rov holati. Iltimos, qaytadan kiring.');
        }

        try {
            $accessToken = $provider->getAccessToken('authorization_code', [
                'code' => $request->string('code')->toString(),
            ]);
            $hemisUser = $provider->getResourceOwner($accessToken)->toArray();

            $user = $this->storeUser($hemisUser);
            $user->update(['degree' => $this->syncWorkplaces($hemisUser)]);

            Auth::login($user);
            $request->session()->regenerate();

            return to_route('home')->with('success', 'Tizimga muvaffaqiyatli kirdingiz!');
        } catch (Throwable $exception) {
            Log::error('HEMIS login failed.', [
                'exception' => $exception,
            ]);

            return to_route('home')->with('error', 'HEMIS orqali kirishda xatolik yuz berdi. Keyinroq qayta urinib ko‘ring.');
        }
    }

    private function provider(): GenericProvider
    {
        $url = rtrim((string) config('services.hemis.url'), '/');

        return new GenericProvider([
            'clientId' => config('services.hemis.client_id'),
            'clientSecret' => config('services.hemis.client_secret'),
            'redirectUri' => config('services.hemis.redirect_uri'),
            'urlAuthorize' => "{$url}/oauth/authorize",
            'urlAccessToken' => "{$url}/oauth/access-token",
            'urlResourceOwnerDetails' => "{$url}/oauth/api/user?fields=id,uuid,employee_id_number,type,roles,name,login,email,picture,picture_full,firstname,surname,patronymic,birth_date,university_id,phone",
        ]);
    }

    private function redirectToHemis(Request $request, GenericProvider $provider): RedirectResponse
    {
        $authorizationUrl = $provider->getAuthorizationUrl();

        $request->session()->put('oauth2state', $provider->getState());

        return redirect()->away($authorizationUrl);
    }

    private function hasValidState(Request $request): bool
    {
        $state = $request->input('state');
        $sessionState = $request->session()->pull('oauth2state');

        return is_string($state)
            && is_string($sessionState)
            && hash_equals($sessionState, $state);
    }

    private function storeUser(array $hemisUser): User
    {
        $userId = data_get($hemisUser, 'employee_id');
        $hemisId = data_get($hemisUser, 'employee_id_number');

        if (! is_numeric($userId) || ! is_numeric($hemisId)) {
            throw new \UnexpectedValueException('HEMIS user response does not contain employee identifiers.');
        }

        $firstName = (string) data_get($hemisUser, 'firstname', '');
        $lastName = (string) data_get($hemisUser, 'surname', '');
        $patronymic = (string) data_get($hemisUser, 'patronymic', '');

        $user = User::firstOrNew(['id' => $userId]);
        $user->fill([
            'hemis_id' => $hemisId,
            'name' => [
                'full' => (string) data_get($hemisUser, 'name', ''),
                'first' => $firstName,
                'last' => $lastName,
                'third' => $patronymic,
                'short' => User::make_short_name($firstName, $lastName, $patronymic),
            ],
            'image' => json_encode([
                'min' => data_get($hemisUser, 'picture'),
                'max' => data_get($hemisUser, 'picture_full'),
            ], JSON_THROW_ON_ERROR),
        ]);

        if (! $user->exists) {
            $user->pos = 'user';
            $user->rol = $userId == 1568 ? ['super_admin', 'user'] : ['user'];
        }

        $user->save();

        return $user;
    }

    private function syncWorkplaces(array $hemisUser): string
    {
        $response = Http::acceptJson()
            ->withToken(config('services.hemis.api_key'))
            ->timeout(10)
            ->retry(2, 200)
            ->get(config('services.hemis.employee_api_url'), [
                'type' => 'all',
                'search' => data_get($hemisUser, 'employee_id_number'),
            ])
            ->throw();

        $degreeType = 'no_degrees';

        foreach (data_get($response->json(), 'data.items', []) as $employee) {
            $departmentId = data_get($employee, 'department.id');

            if (! $departmentId || ! ($department = Department::find($departmentId))) {
                Log::warning('HEMIS workplace skipped because its department was not found.', ['department_id' => $departmentId]);

                continue;
            }

            $workplace = [
                'academic_degree_id' => $this->syncReference(AcademicDegree::class, data_get($employee, 'academicDegree')),
                'academic_rank_id' => $this->syncReference(AcademicRank::class, data_get($employee, 'academicRank')),
                'form_id' => $this->syncReference(EmploymentForm::class, data_get($employee, 'employmentForm')),
                'staff_id' => $this->syncReference(EmploymentStaff::class, data_get($employee, 'employmentStaff')),
                'staff_position_id' => $this->syncReference(StaffPosition::class, data_get($employee, 'staffPosition')),
                'status_id' => $this->syncReference(EmployeeStatus::class, data_get($employee, 'employeeStatus')),
                'type_id' => $this->syncReference(EmployeeType::class, data_get($employee, 'employeeType')),
            ];

            Workplace::updateOrCreate(
                ['user_id' => data_get($employee, 'id'), 'department_id' => $departmentId],
                $workplace,
            );

            if ($workplace['academic_degree_id'] > 10) {
                $degreeType = 'hold_degrees';
            } elseif ($degreeType === 'no_degrees' && $department->evaluation) {
                $degreeType = $department->evaluation;
            }
        }

        return $degreeType;
    }

    /** @param class-string<Model> $model */
    private function syncReference(string $model, mixed $reference): int
    {
        $id = data_get($reference, 'code');
        $name = data_get($reference, 'name');

        if (! is_numeric($id) || ! is_string($name) || $name === '') {
            throw new \UnexpectedValueException("Invalid HEMIS reference for {$model}.");
        }

        return $model::updateOrCreate(['id' => $id], ['name' => $name])->id;
    }
}
