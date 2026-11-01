<?php

namespace Tests\Feature;

use App\Support\Totp;

class AuthTest extends CrmTestCase
{
    public function test_login_and_disabled_account(): void
    {
        $this->post('/login', ['email' => 'c1@x.test', 'password' => 'Secret-pass-1'])->assertRedirect(route('dashboard'));
        $this->post(route('logout'));

        $this->c1->update(['is_active' => false]);
        $this->post('/login', ['email' => 'c1@x.test', 'password' => 'Secret-pass-1'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        foreach (range(1, 5) as $i) {
            $this->post('/login', ['email' => 'c1@x.test', 'password' => 'wrong']);
        }
        $this->post('/login', ['email' => 'c1@x.test', 'password' => 'Secret-pass-1'])->assertStatus(429);
    }

    public function test_disabled_user_is_logged_out_immediately(): void
    {
        $this->actingAs($this->c1)->get(route('dashboard'))->assertOk();
        $this->c1->update(['is_active' => false]);
        $this->actingAs($this->c1->fresh())->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_super_admin_must_set_up_and_use_two_step_login(): void
    {
        config(['crm.require_admin_2fa' => true]);
        $this->actingAs($this->admin)->get(route('dashboard'))->assertRedirect(route('two-factor.setup'));

        $this->get(route('two-factor.setup'))->assertOk();
        $secret = session('2fa:new_secret');
        $this->post(route('two-factor.confirm'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->post(route('two-factor.confirm'), ['code' => Totp::code($secret, intdiv(time(), 30))])->assertRedirect(route('dashboard'));
        $this->assertTrue($this->admin->fresh()->hasTwoFactorEnabled());

        // Next login: password alone is not enough.
        $this->post(route('logout'));
        $this->post('/login', ['email' => 'a@x.test', 'password' => 'Secret-pass-1'])->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();
        $this->post('/two-factor', ['code' => '123456'])->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->post('/two-factor', ['code' => Totp::code($secret, intdiv(time(), 30))])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_totp_matches_rfc_test_vector(): void
    {
        // RFC 6238 SHA-1 vector: secret "12345678901234567890", T=59 -> 94287082 (last 6 digits 287082)
        $this->assertSame('287082', Totp::code('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', intdiv(59, 30)));
    }
}
