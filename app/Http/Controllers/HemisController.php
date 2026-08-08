<?php

namespace App\Http\Controllers;

use App\Actions\DescribeHemisLoginFailure;
use App\Actions\SyncHemisWorkplacesForLogin;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use League\OAuth2\Client\Provider\GenericProvider;
use Throwable;
use UnexpectedValueException;

class HemisController extends Controller
{
    public function index(
        Request $request,
        SyncHemisWorkplacesForLogin $syncHemisWorkplacesForLogin,
        DescribeHemisLoginFailure $describeHemisLoginFailure,
    ): RedirectResponse {
        $provider = $this->provider();

        if (! $request->filled('code')) {
            if ($request->filled('error')) {
                return to_route('login')->with('error', $this->authorizationErrorMessage($request));
            }

            return $this->redirectToHemis($request, $provider);
        }

        if (! $this->hasValidState($request)) {
            return to_route('login')->with(
                'error',
                'Kirish sessiyasi muddati tugagan yoki so‘rov yaroqsiz. Iltimos, qaytadan urinib ko‘ring.',
            );
        }

        try {
            $accessToken = $provider->getAccessToken('authorization_code', [
                'code' => $request->string('code')->toString(),
            ]);
            $hemisUser = $provider->getResourceOwner($accessToken)->toArray();

            $user = $this->storeUser($hemisUser);

            if (! $user->isActive()) {
                return to_route('login')->with(
                    'error',
                    'Hisobingiz administrator tomonidan faolsizlantirilgan. Tizimga kirish taqiqlangan.',
                );
            }

            $user = $syncHemisWorkplacesForLogin->handle($user);

            Auth::login($user);
            $request->session()->regenerate();

            return to_route('home')->with('success', 'Tizimga muvaffaqiyatli kirdingiz!');
        } catch (Throwable $exception) {
            Log::error('HEMIS login failed.', [
                'exception' => $exception,
            ]);

            return to_route('login')->with('error', $describeHemisLoginFailure->handle($exception));
        }
    }

    private function authorizationErrorMessage(Request $request): string
    {
        return match ($request->string('error')->toString()) {
            'access_denied' => 'HEMIS tizimiga kirish bekor qilindi yoki ruxsat berilmadi.',
            'temporarily_unavailable' => 'HEMIS avtorizatsiya xizmati vaqtincha ishlamayapti. Keyinroq qayta urinib ko‘ring.',
            'server_error' => 'HEMIS avtorizatsiya xizmatida ichki xatolik yuz berdi. Keyinroq qayta urinib ko‘ring.',
            default => 'HEMIS avtorizatsiya xizmati kirishni tasdiqlamadi. Qaytadan urinib ko‘ring.',
        };
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
            throw new UnexpectedValueException('HEMIS foydalanuvchi uchun identifikatorlar topilmadi.');
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
            $user->rol = ['teacher'];
            $user->status = '1';
        }

        $user->ensureConfiguredSuperAdminRole();

        $user->save();

        return $user;
    }
}
