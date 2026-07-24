<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class HemisLoginTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_hemis_access_denial_redirects_to_login_with_visible_reason(): void
    {
        $message = 'HEMIS tizimiga kirish bekor qilindi yoki ruxsat berilmadi.';

        $this->get(route('login.user', ['error' => 'access_denied']))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', $message);

        $this->withSession(['error' => $message])
            ->get(route('login'))
            ->assertOk()
            ->assertSee('plugins/toastr/toastr.min.css')
            ->assertSee('plugins/toastr/toastr.min.js')
            ->assertSee('toastr.error', escape: false)
            ->assertSee($message);
    }

    public function test_invalid_oauth_state_redirects_to_login_with_specific_reason(): void
    {
        $this->get(route('login.user', [
            'code' => 'invalid-code',
            'state' => 'invalid-state',
        ]))
            ->assertRedirect(route('login'))
            ->assertSessionHas(
                'error',
                'Kirish sessiyasi muddati tugagan yoki so‘rov yaroqsiz. Iltimos, qaytadan urinib ko‘ring.',
            );
    }

    public function test_login_notification_escapes_untrusted_session_message(): void
    {
        $untrustedMessage = '</script><script>window.loginCompromised = true</script>';

        $this->withSession(['error' => $untrustedMessage])
            ->get(route('login'))
            ->assertOk()
            ->assertSee('toastr.error', escape: false)
            ->assertDontSee($untrustedMessage, escape: false)
            ->assertSee(
                '\u003C\/script\u003E\u003Cscript\u003Ewindow.loginCompromised = true\u003C\/script\u003E',
                escape: false,
            );
    }
}
