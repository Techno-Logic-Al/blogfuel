<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class RecaptchaEnterpriseVerifier
{
    /**
     * Determine whether Enterprise verification is fully configured and enabled.
     */
    public function enabled(): bool
    {
        return (bool) config('services.recaptcha.enterprise.enabled')
            && filled($this->siteKey())
            && filled($this->projectId())
            && filled($this->apiKey());
    }

    /**
     * Return the configured public site key.
     */
    public function siteKey(): ?string
    {
        $value = trim((string) config('services.recaptcha.enterprise.site_key'));

        return $value !== '' ? $value : null;
    }

    /**
     * Verify a token against the Google reCAPTCHA Enterprise API.
     *
     * @return array{passed: bool, message: string, score: float|null}
     */
    public function verify(
        string $token,
        string $expectedAction,
        ?string $userIpAddress = null,
        ?string $userAgent = null
    ): array {
        if (! $this->enabled()) {
            return [
                'passed' => true,
                'message' => '',
                'score' => null,
            ];
        }

        if (trim($token) === '') {
            return [
                'passed' => false,
                'message' => 'Complete the security check and try again.',
                'score' => null,
            ];
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout($this->timeout())
                ->post($this->assessmentUrl(), [
                    'event' => array_filter([
                        'token' => $token,
                        'siteKey' => $this->siteKey(),
                        'expectedAction' => $expectedAction,
                        'userIpAddress' => $userIpAddress,
                        'userAgent' => $userAgent,
                    ], static fn (mixed $value): bool => filled($value)),
                ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Security verification could not be completed right now. Please try again.', previous: $exception);
        }

        if ($response->failed()) {
            throw new RuntimeException('Security verification could not be completed right now. Please try again.');
        }

        $payload = $response->json();

        if (! (bool) data_get($payload, 'tokenProperties.valid', false)) {
            return [
                'passed' => false,
                'message' => 'Security verification failed. Please try again.',
                'score' => null,
            ];
        }

        if ((string) data_get($payload, 'tokenProperties.action', '') !== $expectedAction) {
            return [
                'passed' => false,
                'message' => 'Security verification could not be confirmed. Please try again.',
                'score' => null,
            ];
        }

        $score = (float) data_get($payload, 'riskAnalysis.score', 0);

        if ($score < $this->minimumScore()) {
            return [
                'passed' => false,
                'message' => 'Security verification failed. Please try again.',
                'score' => $score,
            ];
        }

        return [
            'passed' => true,
            'message' => '',
            'score' => $score,
        ];
    }

    /**
     * Return the Google assessment endpoint for the current project.
     */
    protected function assessmentUrl(): string
    {
        return rtrim((string) config('services.recaptcha.enterprise.base_url'), '/')
            .'/projects/'.$this->projectId().'/assessments?key='.$this->apiKey();
    }

    /**
     * Return the Google Cloud project identifier.
     */
    protected function projectId(): ?string
    {
        $value = trim((string) config('services.recaptcha.enterprise.project_id'));

        return $value !== '' ? $value : null;
    }

    /**
     * Return the Google API key used for assessment calls.
     */
    protected function apiKey(): ?string
    {
        $value = trim((string) config('services.recaptcha.enterprise.api_key'));

        return $value !== '' ? $value : null;
    }

    /**
     * Return the minimum accepted score.
     */
    protected function minimumScore(): float
    {
        return max(0, min(1, (float) config('services.recaptcha.enterprise.minimum_score', 0.5)));
    }

    /**
     * Return the Google API timeout in seconds.
     */
    protected function timeout(): int
    {
        return max(3, (int) config('services.recaptcha.enterprise.timeout', 10));
    }
}
