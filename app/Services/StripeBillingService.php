<?php

namespace App\Services;

use App\Models\CreditPurchase;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class StripeBillingService
{
    /**
     * Create a Stripe Checkout session URL for the selected offer.
     */
    public function createCheckoutSession(User $user, string $offerKey): string
    {
        $offer = $this->offer($offerKey);
        $payload = [
            'mode' => $offer['type'] === 'subscription' ? 'subscription' : 'payment',
            'success_url' => route('billing.success').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('posts.index').'#plans',
            'client_reference_id' => (string) $user->getKey(),
            'allow_promotion_codes' => 'true',
            'line_items' => [
                [
                    'price' => $offer['stripe_price_id'],
                    'quantity' => 1,
                ],
            ],
            'metadata' => [
                'user_id' => (string) $user->getKey(),
                'offer' => $offerKey,
                'offer_type' => $offer['type'],
            ],
        ];

        if ($offer['type'] === 'subscription') {
            $payload['subscription_data'] = [
                'metadata' => [
                    'user_id' => (string) $user->getKey(),
                    'offer' => $offerKey,
                    'offer_type' => $offer['type'],
                    'plan' => $offerKey,
                ],
            ];
            $payload['metadata']['plan'] = $offerKey;
        } else {
            $payload['metadata']['credits'] = (string) ($offer['credits'] ?? 0);
        }

        if (filled($user->stripe_customer_id)) {
            $payload['customer'] = $user->stripe_customer_id;
        } else {
            $payload['customer_email'] = $user->email;
        }

        $response = $this->api()->asForm()->post('checkout/sessions', $payload);

        $this->throwIfStripeFailed(
            $response,
            $offer['type'] === 'subscription'
                ? 'Stripe could not start the subscription checkout.'
                : 'Stripe could not start the credit-pack checkout.'
        );

        $checkoutUrl = $response->json('url');

        if (! is_string($checkoutUrl) || $checkoutUrl === '') {
            throw new RuntimeException('Stripe did not return a checkout URL.');
        }

        return $checkoutUrl;
    }

    /**
     * Create a Stripe billing portal session URL.
     */
    public function createBillingPortalSession(User $user): string
    {
        if (! filled($user->stripe_customer_id)) {
            throw new RuntimeException('No Stripe billing profile exists for this account yet.');
        }

        $response = $this->api()->asForm()->post('billing_portal/sessions', [
            'customer' => $user->stripe_customer_id,
            'return_url' => route('posts.index').'#plans',
        ]);

        $this->throwIfStripeFailed($response, 'Stripe could not open the billing portal.');

        $portalUrl = $response->json('url');

        if (! is_string($portalUrl) || $portalUrl === '') {
            throw new RuntimeException('Stripe did not return a billing portal URL.');
        }

        return $portalUrl;
    }

    /**
     * Sync a Checkout session directly after the customer returns from Stripe.
     *
     * @return array<string, mixed>
     */
    public function syncCheckoutSessionForUser(User $user, string $sessionId): array
    {
        $session = $this->fetchCheckoutSession($sessionId);
        $resolvedUser = $this->resolveUserFromCheckoutSession($session);

        if ($resolvedUser !== null && ! $resolvedUser->is($user)) {
            throw new RuntimeException('That checkout session does not belong to the signed-in user.');
        }

        return $this->syncCheckoutSessionData($session, $user);
    }

    /**
     * Verify and process a Stripe webhook payload.
     */
    public function handleWebhook(string $payload, string $signatureHeader): void
    {
        $event = $this->parseWebhookEvent($payload, $signatureHeader);
        $type = (string) ($event['type'] ?? '');
        $object = $event['data']['object'] ?? null;

        if (! is_array($object)) {
            return;
        }

        match ($type) {
            'checkout.session.completed' => $this->handleCheckoutCompleted($object),
            'customer.subscription.created',
            'customer.subscription.updated',
            'customer.subscription.deleted' => $this->syncSubscriptionPayload($object),
            'invoice.paid',
            'invoice.payment_failed' => $this->syncInvoiceSubscription($object),
            default => null,
        };
    }

    /**
     * Retrieve and expand a Checkout session from Stripe.
     *
     * @return array<string, mixed>
     */
    protected function fetchCheckoutSession(string $sessionId): array
    {
        $response = $this->api()->get("checkout/sessions/{$sessionId}", [
            'expand' => ['subscription'],
        ]);

        $this->throwIfStripeFailed($response, 'Stripe could not load the checkout session.');

        $session = $response->json();

        if (! is_array($session)) {
            throw new RuntimeException('Stripe returned an invalid checkout session payload.');
        }

        return $session;
    }

    /**
     * Retrieve a subscription from Stripe.
     *
     * @return array<string, mixed>
     */
    protected function fetchSubscription(string $subscriptionId): array
    {
        $response = $this->api()->get("subscriptions/{$subscriptionId}");

        $this->throwIfStripeFailed($response, 'Stripe could not load the subscription.');

        $subscription = $response->json();

        if (! is_array($subscription)) {
            throw new RuntimeException('Stripe returned an invalid subscription payload.');
        }

        return $subscription;
    }

    /**
     * Keep billing access in sync from a completed Checkout session.
     *
     * @param  array<string, mixed>  $session
     */
    protected function handleCheckoutCompleted(array $session): void
    {
        $user = $this->resolveUserFromCheckoutSession($session);

        if ($user === null) {
            return;
        }

        $this->syncCheckoutSessionData($session, $user);
    }

    /**
     * Apply checkout session data to a user.
     *
     * @param  array<string, mixed>  $session
     * @return array<string, mixed>
     */
    protected function syncCheckoutSessionData(array $session, User $user): array
    {
        $customerId = data_get($session, 'customer');

        $user->forceFill([
            'stripe_customer_id' => is_string($customerId) && $customerId !== '' ? $customerId : $user->stripe_customer_id,
        ])->save();

        if (($session['mode'] ?? null) === 'payment') {
            return array_merge(
                ['type' => 'credit_pack'],
                $this->syncCreditPackCheckout($session, $user),
                ['user' => $user->fresh()]
            );
        }

        $plan = $this->normalizePlanKey(
            (string) (data_get($session, 'metadata.offer') ?: data_get($session, 'metadata.plan'))
        ) ?? $user->subscription_plan;

        $user->forceFill([
            'subscription_plan' => $plan,
        ])->save();

        $subscription = $session['subscription'] ?? null;

        if (is_array($subscription)) {
            $syncedUser = $this->syncSubscriptionPayload($subscription, $user) ?? $user->fresh();

            return [
                'type' => 'subscription',
                'user' => $syncedUser,
            ];
        }

        if (is_string($subscription) && $subscription !== '') {
            $syncedUser = $this->syncSubscriptionById($subscription, $user) ?? $user->fresh();

            return [
                'type' => 'subscription',
                'user' => $syncedUser,
            ];
        }

        return [
            'type' => 'subscription',
            'user' => $user->fresh(),
        ];
    }

    /**
     * Grant a one-time credit pack once for a completed Checkout session.
     *
     * @param  array<string, mixed>  $session
     * @return array{credits_added: int, credited_now: bool}
     */
    protected function syncCreditPackCheckout(array $session, ?User $user = null): array
    {
        $user ??= $this->resolveUserFromCheckoutSession($session);

        if ($user === null || (string) ($session['payment_status'] ?? '') !== 'paid') {
            return [
                'credits_added' => 0,
                'credited_now' => false,
            ];
        }

        $offerKey = $this->normalizeCreditPackKey(
            (string) (data_get($session, 'metadata.offer') ?: data_get($session, 'metadata.plan'))
        );

        if ($offerKey === null) {
            return [
                'credits_added' => 0,
                'credited_now' => false,
            ];
        }

        $credits = max(
            0,
            (int) (config("billing.credit_packs.{$offerKey}.credits")
                ?: data_get($session, 'metadata.credits'))
        );
        $sessionId = (string) ($session['id'] ?? '');

        if ($credits === 0 || $sessionId === '') {
            return [
                'credits_added' => 0,
                'credited_now' => false,
            ];
        }

        $creditedNow = DB::transaction(function () use ($credits, $offerKey, $session, $sessionId, $user): bool {
            $existingPurchase = CreditPurchase::query()
                ->where('stripe_checkout_session_id', $sessionId)
                ->first();

            if ($existingPurchase !== null) {
                return false;
            }

            $lockedUser = User::query()
                ->lockForUpdate()
                ->findOrFail($user->getKey());

            try {
                CreditPurchase::query()->create([
                    'user_id' => $lockedUser->getKey(),
                    'offer_key' => $offerKey,
                    'credits_granted' => $credits,
                    'stripe_checkout_session_id' => $sessionId,
                    'stripe_payment_intent_id' => is_string($session['payment_intent'] ?? null)
                        ? $session['payment_intent']
                        : null,
                    'stripe_customer_id' => is_string(data_get($session, 'customer')) ? data_get($session, 'customer') : null,
                    'stripe_price_id' => config("billing.credit_packs.{$offerKey}.stripe_price_id"),
                    'amount_total' => is_numeric($session['amount_total'] ?? null) ? (int) $session['amount_total'] : null,
                    'currency' => is_string($session['currency'] ?? null) ? strtolower((string) $session['currency']) : null,
                    'status' => is_string($session['payment_status'] ?? null) ? $session['payment_status'] : null,
                ]);
            } catch (QueryException) {
                return false;
            }

            $lockedUser->credit_balance = $lockedUser->creditBalance() + $credits;

            $customerId = data_get($session, 'customer');

            if (is_string($customerId) && $customerId !== '') {
                $lockedUser->stripe_customer_id = $customerId;
            }

            $lockedUser->save();

            return true;
        });

        return [
            'credits_added' => $credits,
            'credited_now' => $creditedNow,
        ];
    }

    /**
     * Sync a subscription referenced by an invoice payload.
     *
     * @param  array<string, mixed>  $invoice
     */
    protected function syncInvoiceSubscription(array $invoice): void
    {
        $subscriptionId = $invoice['subscription'] ?? null;

        if (! is_string($subscriptionId) || $subscriptionId === '') {
            return;
        }

        $this->syncSubscriptionById($subscriptionId, $this->resolveUserFromInvoice($invoice));
    }

    /**
     * Sync a subscription by retrieving it from Stripe first.
     */
    protected function syncSubscriptionById(string $subscriptionId, ?User $user = null): ?User
    {
        return $this->syncSubscriptionPayload(
            $this->fetchSubscription($subscriptionId),
            $user
        );
    }

    /**
     * Apply a subscription payload to the matching user.
     *
     * @param  array<string, mixed>  $subscription
     */
    protected function syncSubscriptionPayload(array $subscription, ?User $user = null): ?User
    {
        $user ??= $this->resolveUserFromSubscription($subscription);

        if ($user === null) {
            return null;
        }

        $priceId = data_get($subscription, 'items.data.0.price.id');
        $status = (string) ($subscription['status'] ?? '');
        $plan = $this->normalizePlanKey(
            (string) (data_get($subscription, 'metadata.offer') ?: data_get($subscription, 'metadata.plan'))
        ) ?? (is_string($priceId) ? $this->inferPlanFromPriceId($priceId) : null)
            ?? $user->subscription_plan;

        $currentPeriodEnd = data_get($subscription, 'current_period_end');
        $endsAt = is_numeric($currentPeriodEnd)
            ? Carbon::createFromTimestamp((int) $currentPeriodEnd)
            : null;

        $customerId = data_get($subscription, 'customer');
        $subscriptionId = $subscription['id'] ?? null;

        $user->forceFill([
            'stripe_customer_id' => is_string($customerId) && $customerId !== '' ? $customerId : $user->stripe_customer_id,
            'stripe_subscription_id' => is_string($subscriptionId) && $subscriptionId !== '' ? $subscriptionId : $user->stripe_subscription_id,
            'stripe_price_id' => is_string($priceId) ? $priceId : $user->stripe_price_id,
            'subscription_status' => $status !== '' ? $status : null,
            'subscription_plan' => $plan,
            'subscription_ends_at' => $endsAt,
        ])->save();

        return $user->fresh();
    }

    /**
     * Resolve a user from a Stripe Checkout session payload.
     *
     * @param  array<string, mixed>  $session
     */
    protected function resolveUserFromCheckoutSession(array $session): ?User
    {
        $userId = data_get($session, 'metadata.user_id') ?: data_get($session, 'client_reference_id');

        if (is_string($userId) && ctype_digit($userId)) {
            $user = User::find((int) $userId);

            if ($user !== null) {
                return $user;
            }
        }

        $customerId = data_get($session, 'customer');

        if (is_string($customerId) && $customerId !== '') {
            $user = User::query()->where('stripe_customer_id', $customerId)->first();

            if ($user !== null) {
                return $user;
            }
        }

        $email = data_get($session, 'customer_details.email') ?: data_get($session, 'customer_email');

        return is_string($email) && $email !== ''
            ? User::query()->where('email', strtolower($email))->first()
            : null;
    }

    /**
     * Resolve a user from a Stripe subscription payload.
     *
     * @param  array<string, mixed>  $subscription
     */
    protected function resolveUserFromSubscription(array $subscription): ?User
    {
        $userId = data_get($subscription, 'metadata.user_id');

        if (is_string($userId) && ctype_digit($userId)) {
            $user = User::find((int) $userId);

            if ($user !== null) {
                return $user;
            }
        }

        $customerId = data_get($subscription, 'customer');

        if (is_string($customerId) && $customerId !== '') {
            $user = User::query()->where('stripe_customer_id', $customerId)->first();

            if ($user !== null) {
                return $user;
            }
        }

        $subscriptionId = $subscription['id'] ?? null;

        return is_string($subscriptionId) && $subscriptionId !== ''
            ? User::query()->where('stripe_subscription_id', $subscriptionId)->first()
            : null;
    }

    /**
     * Resolve a user from a Stripe invoice payload.
     *
     * @param  array<string, mixed>  $invoice
     */
    protected function resolveUserFromInvoice(array $invoice): ?User
    {
        $customerId = data_get($invoice, 'customer');

        if (is_string($customerId) && $customerId !== '') {
            $user = User::query()->where('stripe_customer_id', $customerId)->first();

            if ($user !== null) {
                return $user;
            }
        }

        $subscriptionId = data_get($invoice, 'subscription');

        return is_string($subscriptionId) && $subscriptionId !== ''
            ? User::query()->where('stripe_subscription_id', $subscriptionId)->first()
            : null;
    }

    /**
     * Resolve a configured billing offer.
     *
     * @return array<string, mixed>
     */
    protected function offer(string $offerKey): array
    {
        $plan = config("billing.plans.{$offerKey}");

        if (is_array($plan)) {
            if (! filled($plan['stripe_price_id'] ?? null)) {
                throw new RuntimeException('That Stripe plan is not configured yet.');
            }

            return [
                'type' => 'subscription',
                ...$plan,
            ];
        }

        $creditPack = config("billing.credit_packs.{$offerKey}");

        if (is_array($creditPack)) {
            if (! filled($creditPack['stripe_price_id'] ?? null)) {
                throw new RuntimeException('That Stripe credit pack is not configured yet.');
            }

            return [
                'type' => 'credit_pack',
                ...$creditPack,
            ];
        }

        throw new RuntimeException('That billing option is not available.');
    }

    /**
     * Infer the plan key from a Stripe price identifier.
     */
    protected function inferPlanFromPriceId(string $priceId): ?string
    {
        foreach (config('billing.plans', []) as $planKey => $plan) {
            if (($plan['stripe_price_id'] ?? null) === $priceId) {
                return $planKey;
            }
        }

        return null;
    }

    /**
     * Normalize the plan key to a configured plan.
     */
    protected function normalizePlanKey(string $planKey): ?string
    {
        return is_array(config("billing.plans.{$planKey}")) ? $planKey : null;
    }

    /**
     * Normalize the credit-pack key to a configured one-time offer.
     */
    protected function normalizeCreditPackKey(string $offerKey): ?string
    {
        return is_array(config("billing.credit_packs.{$offerKey}")) ? $offerKey : null;
    }

    /**
     * Create a reusable Stripe API client.
     */
    protected function api(): PendingRequest
    {
        $secret = trim((string) config('services.stripe.secret'));

        if ($secret === '') {
            throw new RuntimeException('Add STRIPE_SECRET to enable Stripe billing.');
        }

        return Http::baseUrl((string) config('services.stripe.base_url', 'https://api.stripe.com/v1'))
            ->acceptJson()
            ->withBasicAuth($secret, '')
            ->timeout((int) config('services.stripe.timeout', 30));
    }

    /**
     * Throw a descriptive exception for failed Stripe API responses.
     */
    protected function throwIfStripeFailed(mixed $response, string $fallbackMessage): void
    {
        if (! method_exists($response, 'failed') || ! $response->failed()) {
            return;
        }

        $message = $response->json('error.message');

        throw new RuntimeException(
            is_string($message) && $message !== '' ? $message : $fallbackMessage
        );
    }

    /**
     * Parse and verify a Stripe webhook event.
     *
     * @return array<string, mixed>
     */
    protected function parseWebhookEvent(string $payload, string $signatureHeader): array
    {
        $secret = trim((string) config('services.stripe.webhook_secret'));

        if ($secret === '') {
            throw new RuntimeException('Add STRIPE_WEBHOOK_SECRET before accepting Stripe webhooks.');
        }

        if ($signatureHeader === '') {
            throw new RuntimeException('The Stripe-Signature header is missing.');
        }

        [$timestamp, $signatures] = $this->extractStripeSignatures($signatureHeader);

        if (abs(time() - $timestamp) > 300) {
            throw new RuntimeException('The Stripe webhook signature timestamp is out of tolerance.');
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        $valid = collect($signatures)->contains(
            fn (string $signature): bool => hash_equals($expected, $signature)
        );

        if (! $valid) {
            throw new RuntimeException('The Stripe webhook signature could not be verified.');
        }

        $event = json_decode($payload, true);

        if (! is_array($event)) {
            throw new RuntimeException('Stripe sent an invalid webhook payload.');
        }

        return $event;
    }

    /**
     * Extract the timestamp and v1 signatures from the Stripe header.
     *
     * @return array{0: int, 1: array<int, string>}
     */
    protected function extractStripeSignatures(string $signatureHeader): array
    {
        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $signatureHeader) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);

            if ($key === 't' && is_numeric($value)) {
                $timestamp = (int) $value;
            }

            if ($key === 'v1' && is_string($value) && $value !== '') {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || $signatures === []) {
            throw new RuntimeException('The Stripe-Signature header is incomplete.');
        }

        return [$timestamp, $signatures];
    }
}
