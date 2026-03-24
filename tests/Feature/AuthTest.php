<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
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
            ->assertSee('data-password-toggle="login_password"', false);
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
