<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\RecaptchaEnterpriseVerifier;
use Closure;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            'password' => [
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
            ],
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
}
