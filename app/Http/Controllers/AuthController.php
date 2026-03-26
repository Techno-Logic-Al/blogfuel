<?php

namespace App\Http\Controllers;

use Illuminate\Auth\Events\PasswordReset;
use App\Models\User;
use App\Services\RecaptchaEnterpriseVerifier;
use Closure;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class AuthController extends Controller
{
    /**
     * Display the login form.
     */
    public function showLogin(): View
    {
        return view('auth.login');
    }

    /**
     * Display the password reset request form.
     */
    public function showForgotPassword(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Email a password reset link to the matching user.
     */
    public function sendPasswordResetLink(Request $request, RecaptchaEnterpriseVerifier $recaptcha): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email:rfc'],
        ]);

        if ($response = $this->verifyRecaptcha($request, $recaptcha, 'PASSWORD_RESET_REQUEST')) {
            return $response;
        }

        try {
            $status = Password::sendResetLink([
                'email' => $this->normalizeEmail($validated['email']),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors([
                    'email' => 'Password reset email could not be sent right now. Check the mail settings and try again.',
                ]);
        }

        if ($status !== Password::RESET_LINK_SENT) {
            return back()
                ->withInput()
                ->withErrors([
                    'email' => $this->passwordStatusMessage($status),
                ]);
        }

        return back()->with('status', 'A password reset link has been sent to your email address.');
    }

    /**
     * Display the password reset form.
     */
    public function showResetPassword(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    /**
     * Reset a user's password using a valid email token.
     */
    public function resetPassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email:rfc'],
            'password' => $this->passwordRules(),
        ]);

        $status = Password::reset([
            'email' => $this->normalizeEmail($validated['email']),
            'password' => $validated['password'],
            'password_confirmation' => (string) $request->input('password_confirmation', ''),
            'token' => $validated['token'],
        ], function (User $user, string $password): void {
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();

            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors([
                    'email' => $this->passwordStatusMessage($status),
                ]);
        }

        return redirect()
            ->route('login')
            ->with('status', 'Your password has been reset. You can now sign in with the new password.');
    }

    /**
     * Authenticate a user by username and password.
     */
    public function login(Request $request, RecaptchaEnterpriseVerifier $recaptcha): RedirectResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if ($response = $this->verifyRecaptcha($request, $recaptcha, 'LOGIN', 'recaptcha', ['password'])) {
            return $response;
        }

        $username = Str::lower(trim($validated['username']));

        if (! Auth::attempt(['name' => $username, 'password' => $validated['password']])) {
            return back()
                ->withInput($request->except('password'))
                ->withErrors([
                    'username' => 'These credentials do not match our records.',
                ]);
        }

        $request->session()->regenerate();

        $hasGuestPreview = $request->session()->has(PostController::GUEST_PREVIEW_SESSION_KEY);

        if (! $request->user()?->hasVerifiedEmail()) {
            return redirect()
                ->route('verification.notice')
                ->with('status', $hasGuestPreview
                    ? 'Verify your email to publish your saved guest draft and keep using BlogFuel.'
                    : 'Verify your email to start using BlogFuel.');
        }

        return redirect()
            ->intended(route('posts.index'))
            ->with('status', $hasGuestPreview
                ? 'Logged in successfully. Your guest draft is ready to publish.'
                : 'Logged in successfully.');
    }

    /**
     * Display the registration form.
     */
    public function showRegister(): View
    {
        return view('auth.register');
    }

    /**
     * Display the authenticated password change form.
     */
    public function showChangePassword(): View
    {
        return view('auth.change-password');
    }

    /**
     * Check whether a username is available for registration.
     */
    public function checkUsername(Request $request): JsonResponse
    {
        $username = Str::lower(trim((string) $request->query('username', '')));

        if (mb_strlen($username) < 3) {
            return response()->json([
                'available' => false,
                'valid' => false,
                'message' => 'Enter at least 3 characters.',
            ]);
        }

        if (mb_strlen($username) > 30 || ! preg_match('/^[A-Za-z0-9_-]+$/', $username)) {
            return response()->json([
                'available' => false,
                'valid' => false,
                'message' => 'Use only letters, numbers, hyphens, or underscores.',
            ]);
        }

        $available = ! User::query()
            ->where('name', $username)
            ->exists();

        return response()->json([
            'available' => $available,
            'valid' => true,
            'message' => $available ? 'Username is available.' : 'Username has already been taken.',
        ]);
    }

    /**
     * Display the email verification prompt.
     */
    public function showVerifyNotice(Request $request): View|RedirectResponse
    {
        if ($request->user()?->hasVerifiedEmail()) {
            return redirect()->route('posts.index');
        }

        return view('auth.verify-email');
    }

    /**
     * Mark the authenticated user's email address as verified.
     */
    public function verifyEmail(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        return redirect()
            ->route('posts.index')
            ->with('status', $request->session()->has(PostController::GUEST_PREVIEW_SESSION_KEY)
                ? 'Email verified. Your guest draft is ready to publish.'
                : 'Email verified. You can now generate articles.');
    }

    /**
     * Resend the verification email.
     */
    public function sendVerificationEmail(Request $request, RecaptchaEnterpriseVerifier $recaptcha): RedirectResponse
    {
        if ($request->user()?->hasVerifiedEmail()) {
            return redirect()->route('posts.index');
        }

        if ($response = $this->verifyRecaptcha($request, $recaptcha, 'RESEND_VERIFICATION')) {
            return $response;
        }

        try {
            $request->user()?->sendEmailVerificationNotification();
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'email' => 'Verification email could not be sent right now. Check the mail settings and try again.',
            ]);
        }

        return back()->with('status', 'A fresh verification link has been sent to your email address.');
    }

    /**
     * Register a new user account.
     */
    public function register(Request $request, RecaptchaEnterpriseVerifier $recaptcha): RedirectResponse
    {
        $validated = $request->validate([
            'username' => [
                'required',
                'string',
                'min:3',
                'max:30',
                'alpha_dash:ascii',
                Rule::unique('users', 'name'),
            ],
            'email' => ['required', 'string', 'email:rfc', Rule::unique('users', 'email')],
            'password' => $this->passwordRules(),
        ]);

        if ($response = $this->verifyRecaptcha($request, $recaptcha, 'REGISTER', 'recaptcha', ['password', 'password_confirmation'])) {
            return $response;
        }

        $username = Str::lower(trim($validated['username']));
        $email = Str::lower(trim($validated['email']));

        $user = User::create([
            'name' => $username,
            'email' => $email,
            'password' => $validated['password'],
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        try {
            $user->sendEmailVerificationNotification();
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('verification.notice')
                ->withErrors([
                    'email' => 'Account created, but the verification email could not be sent right now. Check the mail settings and resend it from this page.',
                ]);
        }

        return redirect()
            ->route('verification.notice')
            ->with('status', $request->session()->has(PostController::GUEST_PREVIEW_SESSION_KEY)
                ? 'Account created. Check your email to verify your address, then publish your saved guest draft.'
                : 'Account created. Check your email to verify your address before using BlogFuel.');
    }

    /**
     * Change the authenticated user's password.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => array_merge(
                ['different:current_password'],
                $this->passwordRules()
            ),
        ]);

        $request->user()?->forceFill([
            'password' => $validated['password'],
            'remember_token' => Str::random(60),
        ])->save();

        return redirect()
            ->route('password.change')
            ->with('status', 'Your password has been updated.');
    }

    /**
     * Log the current user out.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('posts.index')
            ->with('status', 'Logged out successfully.');
    }

    /**
     * Normalize an email address before querying or storing it.
     */
    protected function normalizeEmail(string $email): string
    {
        return Str::lower(trim($email));
    }

    /**
     * Return the shared password validation rules.
     *
     * @return array<int, string|Closure>
     */
    protected function passwordRules(): array
    {
        return [
            'required',
            'string',
            'confirmed',
            'min:8',
            function (string $attribute, mixed $value, Closure $fail): void {
                $password = is_string($value) ? $value : '';

                if (! preg_match('/[A-Z]/', $password)) {
                    $fail('Password must include at least one uppercase letter.');
                }

                if (! preg_match('/\d/', $password)) {
                    $fail('Password must include at least one number.');
                }

                if (! preg_match('/[^A-Za-z0-9]/', $password)) {
                    $fail('Password must include at least one special symbol.');
                }
            },
        ];
    }

    /**
     * Map password broker status codes to user-facing copy.
     */
    protected function passwordStatusMessage(string $status): string
    {
        return match ($status) {
            Password::INVALID_USER => 'We could not find an account for that email address.',
            Password::INVALID_TOKEN => 'This password reset link is invalid or has expired.',
            Password::RESET_THROTTLED => 'A password reset email was sent recently. Please wait before trying again.',
            default => __($status),
        };
    }
}
