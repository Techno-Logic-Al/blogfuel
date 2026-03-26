<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Guests should be able to open the login page.
     */
    public function test_login_page_loads(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Login')
            ->assertSee('Username')
            ->assertSee('Forgot your password?')
            ->assertSee('data-password-toggle="login_password"', false);
    }

    /**
     * Guests should be able to open the password reset request page.
     */
    public function test_forgot_password_page_loads(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Forgot your password?')
            ->assertSee('Email address')
            ->assertSee('Send reset email');
    }

    /**
     * Guests should be able to register and receive a verification email.
     */
    public function test_guest_can_register(): void
    {
        Notification::fake();

        $response = $this->post(route('register.store'), [
            'username' => 'writer01',
            'email' => 'writer01@example.com',
            'password' => 'Strong#123',
            'password_confirmation' => 'Strong#123',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $this->assertAuthenticated();

        $user = User::query()->where('name', 'writer01')->first();

        $this->assertNotNull($user);
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertDatabaseHas('users', [
            'name' => 'writer01',
            'email' => 'writer01@example.com',
        ]);

        Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $notification, array $channels) use ($user): bool {
            $mailMessage = $notification->toMail($user);

            return in_array('mail', $channels, true)
                && $mailMessage->subject === 'Verify your BlogFuel email address'
                && $mailMessage->actionText === 'Verify email address'
                && in_array('Confirm your email address to unlock article generation, publishing, and sharing in BlogFuel.', $mailMessage->introLines, true);
        });
    }

    /**
     * Guests should be able to request a password reset email.
     */
    public function test_guest_can_request_password_reset_email(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'name' => 'reset-user',
            'email' => 'reset-user@example.com',
        ]);

        $response = $this->from(route('password.request'))->post(route('password.email'), [
            'email' => 'reset-user@example.com',
        ]);

        $response->assertRedirect(route('password.request'));
        $response->assertSessionHas('status', 'A password reset link has been sent to your email address.');

        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification, array $channels) use ($user): bool {
            $mailMessage = $notification->toMail($user);

            return in_array('mail', $channels, true)
                && $mailMessage->subject === 'Reset your BlogFuel password'
                && $mailMessage->actionText === 'Reset password';
        });
    }

    /**
     * Password reset requests should require a reCAPTCHA token when Enterprise protection is enabled.
     */
    public function test_password_reset_request_requires_recaptcha_when_enabled(): void
    {
        $this->enableRecaptcha();
        Notification::fake();

        User::factory()->create([
            'name' => 'reset-captcha-user',
            'email' => 'reset-captcha@example.com',
        ]);

        $response = $this->from(route('password.request'))->post(route('password.email'), [
            'email' => 'reset-captcha@example.com',
        ]);

        $response->assertRedirect(route('password.request'));
        $response->assertSessionHasErrors([
            'recaptcha' => 'Complete the security check and try again.',
        ]);
        Notification::assertNothingSent();
    }

    /**
     * A broken mail transport should not crash the password reset email flow.
     */
    public function test_password_reset_email_failure_is_handled_gracefully(): void
    {
        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.scheme', 'tls');

        User::factory()->create([
            'name' => 'reset-broken',
            'email' => 'reset-broken@example.com',
        ]);

        $response = $this->from(route('password.request'))->post(route('password.email'), [
            'email' => 'reset-broken@example.com',
        ]);

        $response->assertRedirect(route('password.request'));
        $response->assertSessionHasErrors([
            'email' => 'Password reset email could not be sent right now. Check the mail settings and try again.',
        ]);
    }

    /**
     * Guests should be able to open the password reset form from a valid link.
     */
    public function test_reset_password_page_loads_from_valid_link(): void
    {
        $user = User::factory()->create([
            'name' => 'reset-form-user',
            'email' => 'reset-form@example.com',
        ]);

        $token = Password::broker()->createToken($user);

        $this->get(route('password.reset', [
            'token' => $token,
            'email' => 'reset-form@example.com',
        ]))
            ->assertOk()
            ->assertSee('Reset password')
            ->assertSee('data-password-toggle="reset_password"', false)
            ->assertSee('data-password-toggle="reset_password_confirmation"', false);
    }

    /**
     * Guests should be able to reset a password using a valid token.
     */
    public function test_guest_can_reset_password_with_valid_token(): void
    {
        $user = User::factory()->create([
            'name' => 'password-reset-user',
            'email' => 'password-reset@example.com',
            'password' => 'Old#1234',
        ]);

        $token = Password::broker()->createToken($user);

        $response = $this->post(route('password.store'), [
            'token' => $token,
            'email' => 'password-reset@example.com',
            'password' => 'New#1234',
            'password_confirmation' => 'New#1234',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status', 'Your password has been reset. You can now sign in with the new password.');
        $this->assertTrue(Hash::check('New#1234', $user->fresh()->password));
    }

    /**
     * The registration form should expose password visibility toggles.
     */
    public function test_register_page_has_password_visibility_toggles(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Email address')
            ->assertSee('data-password-toggle="register_password"', false)
            ->assertSee('data-password-toggle="register_password_confirmation"', false)
            ->assertSee('At least 8 characters')
            ->assertSee('At least 1 uppercase letter')
            ->assertSee('At least 1 number')
            ->assertSee('At least 1 special symbol');
    }

    /**
     * Email is required for registration.
     */
    public function test_guest_cannot_register_without_email(): void
    {
        $response = $this->from(route('register'))->post(route('register.store'), [
            'username' => 'writer03',
            'password' => 'Strong#123',
            'password_confirmation' => 'Strong#123',
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    /**
     * Weak passwords should be rejected.
     */
    public function test_guest_cannot_register_with_weak_password(): void
    {
        $response = $this->from(route('register'))->post(route('register.store'), [
            'username' => 'writer02',
            'email' => 'writer02@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    /**
     * Authenticated users should be able to open the change-password page.
     */
    public function test_authenticated_user_can_view_change_password_page(): void
    {
        $user = User::factory()->create([
            'name' => 'change-password-user',
            'email' => 'change-password@example.com',
            'password' => 'Old#1234',
        ]);

        $this->actingAs($user)
            ->get(route('password.change'))
            ->assertOk()
            ->assertSee('Change password')
            ->assertSee('Current password')
            ->assertSee('data-password-toggle="current_password"', false)
            ->assertSee('data-password-toggle="change_password"', false)
            ->assertSee('data-password-toggle="change_password_confirmation"', false);
    }

    /**
     * Authenticated users should be able to change their password.
     */
    public function test_authenticated_user_can_change_password(): void
    {
        $user = User::factory()->create([
            'name' => 'change-password-submit-user',
            'email' => 'change-password-submit@example.com',
            'password' => 'Old#1234',
        ]);

        $response = $this->actingAs($user)
            ->from(route('password.change'))
            ->put(route('password.update'), [
                'current_password' => 'Old#1234',
                'password' => 'New#1234',
                'password_confirmation' => 'New#1234',
            ]);

        $response->assertRedirect(route('password.change'));
        $response->assertSessionHas('status', 'Your password has been updated.');
        $this->assertTrue(Hash::check('New#1234', $user->fresh()->password));
    }

    /**
     * Users should not be able to change password without the current password.
     */
    public function test_authenticated_user_cannot_change_password_with_wrong_current_password(): void
    {
        $user = User::factory()->create([
            'name' => 'change-password-wrong-current',
            'email' => 'change-password-wrong-current@example.com',
            'password' => 'Old#1234',
        ]);

        $response = $this->actingAs($user)
            ->from(route('password.change'))
            ->put(route('password.update'), [
                'current_password' => 'Wrong#1234',
                'password' => 'New#1234',
                'password_confirmation' => 'New#1234',
            ]);

        $response->assertRedirect(route('password.change'));
        $response->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('Old#1234', $user->fresh()->password));
    }

    /**
     * Registration should require a reCAPTCHA token when Enterprise protection is enabled.
     */
    public function test_guest_cannot_register_without_recaptcha_when_enabled(): void
    {
        $this->enableRecaptcha();

        $response = $this->from(route('register'))->post(route('register.store'), [
            'username' => 'writer02secure',
            'email' => 'writer02secure@example.com',
            'password' => 'Strong#123',
            'password_confirmation' => 'Strong#123',
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors([
            'recaptcha' => 'Complete the security check and try again.',
        ]);
        $this->assertGuest();
    }

    /**
     * Guests should be able to check username availability.
     */
    public function test_username_check_returns_available_for_new_username(): void
    {
        $this->getJson(route('register.username.check', ['username' => 'writer01']))
            ->assertOk()
            ->assertJson([
                'available' => true,
                'valid' => true,
                'message' => 'Username is available.',
            ]);
    }

    /**
     * Guests should be told when a username is taken.
     */
    public function test_username_check_returns_taken_for_existing_username(): void
    {
        User::factory()->create([
            'name' => 'admin',
            'email' => 'admin@blogfuel.local',
        ]);

        $this->getJson(route('register.username.check', ['username' => 'admin']))
            ->assertOk()
            ->assertJson([
                'available' => false,
                'valid' => true,
                'message' => 'Username has already been taken.',
            ]);
    }

    /**
     * Users should be able to log in with their username.
     */
    public function test_user_can_log_in_with_username(): void
    {
        User::factory()->create([
            'name' => 'admin',
            'email' => 'admin@blogfuel.local',
            'password' => 'password',
        ]);

        $response = $this->post(route('login.attempt'), [
            'username' => 'admin',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('posts.index'));
        $this->assertAuthenticated();
    }

    /**
     * Login should require a reCAPTCHA token when Enterprise protection is enabled.
     */
    public function test_login_requires_recaptcha_when_enabled(): void
    {
        $this->enableRecaptcha();

        User::factory()->create([
            'name' => 'recaptcha-login',
            'email' => 'recaptcha-login@example.com',
            'password' => 'password',
        ]);

        $response = $this->from(route('login'))->post(route('login.attempt'), [
            'username' => 'recaptcha-login',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors([
            'recaptcha' => 'Complete the security check and try again.',
        ]);
        $this->assertGuest();
    }

    /**
     * Unverified users should be routed to the verification notice after login.
     */
    public function test_unverified_user_is_redirected_to_email_verification_notice_after_login(): void
    {
        $user = User::factory()->unverified()->create([
            'name' => 'writer04',
            'email' => 'writer04@example.com',
            'password' => 'password',
        ]);

        $response = $this->post(route('login.attempt'), [
            'username' => 'writer04',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $this->assertAuthenticatedAs($user);
    }

    /**
     * Authenticated unverified users should be able to view the verification notice.
     */
    public function test_unverified_user_can_view_the_verification_notice(): void
    {
        $user = User::factory()->unverified()->create([
            'name' => 'writer05',
            'email' => 'writer05@example.com',
        ]);

        $this->actingAs($user)
            ->get(route('verification.notice'))
            ->assertOk()
            ->assertSee('Verify your email')
            ->assertSee('writer05@example.com');
    }

    /**
     * Authenticated unverified users should be able to request a fresh verification email.
     */
    public function test_unverified_user_can_request_a_fresh_verification_email(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create([
            'name' => 'writer06',
            'email' => 'writer06@example.com',
        ]);

        $response = $this->actingAs($user)
            ->from(route('verification.notice'))
            ->post(route('verification.send'));

        $response->assertRedirect(route('verification.notice'));
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    /**
     * Verification-email resend should require a reCAPTCHA token when Enterprise protection is enabled.
     */
    public function test_unverified_user_cannot_resend_verification_email_without_recaptcha_when_enabled(): void
    {
        $this->enableRecaptcha();
        Notification::fake();

        $user = User::factory()->unverified()->create([
            'name' => 'writer06captcha',
            'email' => 'writer06captcha@example.com',
        ]);

        $response = $this->actingAs($user)
            ->from(route('verification.notice'))
            ->post(route('verification.send'));

        $response->assertRedirect(route('verification.notice'));
        $response->assertSessionHasErrors([
            'recaptcha' => 'Complete the security check and try again.',
        ]);
        Notification::assertNothingSent();
    }

    /**
     * A broken mail transport should not crash the resend verification flow.
     */
    public function test_unverified_user_sees_friendly_error_if_verification_email_cannot_be_sent(): void
    {
        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.scheme', 'tls');

        $user = User::factory()->unverified()->create([
            'name' => 'writer06broken',
            'email' => 'writer06broken@example.com',
        ]);

        $response = $this->actingAs($user)
            ->from(route('verification.notice'))
            ->post(route('verification.send'));

        $response->assertRedirect(route('verification.notice'));
        $response->assertSessionHasErrors([
            'email' => 'Verification email could not be sent right now. Check the mail settings and try again.',
        ]);
    }

    /**
     * A broken mail transport should not crash registration after the account is created.
     */
    public function test_guest_registration_handles_verification_mail_failure_gracefully(): void
    {
        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.scheme', 'tls');

        $response = $this->post(route('register.store'), [
            'username' => 'writer08',
            'email' => 'writer08@example.com',
            'password' => 'Strong#123',
            'password_confirmation' => 'Strong#123',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $response->assertSessionHasErrors([
            'email' => 'Account created, but the verification email could not be sent right now. Check the mail settings and resend it from this page.',
        ]);
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'name' => 'writer08',
            'email' => 'writer08@example.com',
        ]);
    }

    /**
     * Signed verification links should mark the user's email as verified.
     */
    public function test_signed_verification_link_marks_the_email_as_verified(): void
    {
        $user = User::factory()->unverified()->create([
            'name' => 'writer07',
            'email' => 'writer07@example.com',
        ]);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ],
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        $response->assertRedirect(route('posts.index'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    /**
     * Authenticated users should be able to log out.
     */
    public function test_authenticated_user_can_log_out(): void
    {
        $user = User::factory()->create([
            'name' => 'admin',
            'email' => 'admin@blogfuel.local',
            'password' => 'password',
        ]);

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('posts.index'));
        $this->assertGuest();
    }

    /**
     * Enable Enterprise reCAPTCHA protection for route-level tests.
     */
    protected function enableRecaptcha(): void
    {
        Config::set('services.recaptcha.enterprise.enabled', true);
        Config::set('services.recaptcha.enterprise.site_key', 'test-site-key');
        Config::set('services.recaptcha.enterprise.api_key', 'test-api-key');
        Config::set('services.recaptcha.enterprise.project_id', 'blogfuel');
    }
}
