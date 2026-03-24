<?php

namespace App\Http\Controllers;

use App\Services\RecaptchaEnterpriseVerifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

abstract class Controller
{
    /**
     * Validate a reCAPTCHA Enterprise token for the current request.
     */
    protected function verifyRecaptcha(
        Request $request,
        RecaptchaEnterpriseVerifier $recaptcha,
        string $action,
        string $errorKey = 'recaptcha',
        array $exceptInput = []
    ): ?RedirectResponse {
        if (! $recaptcha->enabled()) {
            return null;
        }

        try {
            $result = $recaptcha->verify(
                token: (string) $request->input('recaptcha_token', ''),
                expectedAction: $action,
                userIpAddress: $request->ip(),
                userAgent: $request->userAgent(),
            );
        } catch (RuntimeException $exception) {
            return back()
                ->withInput($request->except($exceptInput))
                ->withErrors([$errorKey => $exception->getMessage()]);
        }

        if ($result['passed']) {
            return null;
        }

        return back()
            ->withInput($request->except($exceptInput))
            ->withErrors([$errorKey => $result['message']]);
    }
}
