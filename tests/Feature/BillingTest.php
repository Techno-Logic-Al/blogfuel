<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verified users under the quota should see their remaining free generations.
     */
    public function test_verified_user_sees_remaining_free_generations(): void
    {
        $user = User::factory()->create([
            'generated_posts_count' => 2,
        ]);

        $this->actingAs($user)
            ->get(route('posts.index'))
            ->assertOk()
            ->assertSee('3 of 5 free generations remaining')
            ->assertSee('GPT-5.2')
            ->assertDontSee('value="gpt-5.4"', false)
            ->assertSee('Generate and publish');
    }

    /**
     * Users who exhaust the free quota should see the credit-pack and Pro-plan choices.
     */
    public function test_user_who_reaches_the_free_limit_sees_paid_plan_choices(): void
    {
        $user = User::factory()->create([
            'generated_posts_count' => 5,
        ]);

        $response = $this->actingAs($user)
            ->get(route('posts.index'))
            ->assertOk()
            ->assertSee('Your free articles are used up.')
            ->assertSee('Choose article credits for occasional use, or switch to Pro for unlimited generation and GPT-5.4 access.')
            ->assertSee('25-Article Pack')
            ->assertSee('100-Article Pack')
            ->assertSee('No subscription')
            ->assertSee('Pro Monthly')
            ->assertSee('Pro Annual')
            ->assertSee('Unlimited access')
            ->assertDontSee('Generate and publish');

        $content = $response->getContent();

        $this->assertIsString($content);
        $this->assertSame(1, substr_count($content, 'Best pack value'));
        $this->assertSame(1, substr_count($content, 'Best overall value'));
    }

    /**
     * Users with paid credits should keep generator access after the free tier is exhausted.
     */
    public function test_user_with_credit_balance_can_still_generate_after_free_quota_is_used(): void
    {
        $user = User::factory()->create([
            'generated_posts_count' => 5,
            'credit_balance' => 25,
        ]);

        $this->actingAs($user)
            ->get(route('posts.index'))
            ->assertOk()
            ->assertSee('Paid usage')
            ->assertSee('25 paid posts remaining')
            ->assertSee('25 paid posts left')
            ->assertSee('You are now generating with your prepaid article credits. Keep going with your paid balance, or switch to Pro to unlock unlimited access and GPT-5.4.')
            ->assertDontSee('Free usage')
            ->assertSee('Generate and publish')
            ->assertSee('GPT-5.2')
            ->assertDontSee('value="gpt-5.4"', false);
    }

    /**
     * The seeded admin account should bypass the free-generation limit entirely.
     */
    public function test_admin_account_has_unlimited_free_generation_access(): void
    {
        $user = User::factory()->create([
            'name' => 'admin',
            'email' => 'admin-unlimited@example.com',
            'generated_posts_count' => 500,
        ]);

        $this->actingAs($user)
            ->get(route('posts.index'))
            ->assertOk()
            ->assertSee('Unlimited free article generation')
            ->assertSee('This admin account bypasses the normal free-generation cap and does not require a subscription.')
            ->assertSee('Generate and publish')
            ->assertDontSee('Your 5 free generations are used up.')
            ->assertDontSee('Choose plan');
    }

    /**
     * The checkout route should send the user to Stripe Checkout.
     */
    public function test_checkout_route_redirects_to_stripe_checkout(): void
    {
        Config::set('services.stripe.secret', 'sk_test_123');
        Config::set('billing.plans.monthly.stripe_price_id', 'price_monthly_123');

        $user = User::factory()->create([
            'generated_posts_count' => 5,
        ]);

        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions' => Http::response([
                'url' => 'https://checkout.stripe.com/c/pay/test_monthly',
            ]),
        ]);

        $response = $this->actingAs($user)->post(route('billing.checkout', 'monthly'));

        $response->assertRedirect('https://checkout.stripe.com/c/pay/test_monthly');

        Http::assertSent(function ($request) use ($user): bool {
            $data = $request->data();

            return $request->url() === 'https://api.stripe.com/v1/checkout/sessions'
                && ($data['mode'] ?? null) === 'subscription'
                && ($data['client_reference_id'] ?? null) === (string) $user->getKey()
                && ($data['allow_promotion_codes'] ?? null) === 'true'
                && data_get($data, 'line_items.0.price') === 'price_monthly_123'
                && data_get($data, 'metadata.offer') === 'monthly'
                && data_get($data, 'metadata.offer_type') === 'subscription'
                && data_get($data, 'subscription_data.metadata.user_id') === (string) $user->getKey()
                && data_get($data, 'subscription_data.metadata.offer') === 'monthly';
        });
    }

    /**
     * Credit-pack checkout should use Stripe payment mode.
     */
    public function test_credit_pack_checkout_route_redirects_to_stripe_payment_checkout(): void
    {
        Config::set('services.stripe.secret', 'sk_test_123');
        Config::set('billing.credit_packs.pack_25.stripe_price_id', 'price_pack_25_123');

        $user = User::factory()->create([
            'generated_posts_count' => 5,
        ]);

        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions' => Http::response([
                'url' => 'https://checkout.stripe.com/c/pay/test_pack_25',
            ]),
        ]);

        $response = $this->actingAs($user)->post(route('billing.checkout', 'pack_25'));

        $response->assertRedirect('https://checkout.stripe.com/c/pay/test_pack_25');

        Http::assertSent(function ($request) use ($user): bool {
            $data = $request->data();

            return $request->url() === 'https://api.stripe.com/v1/checkout/sessions'
                && ($data['mode'] ?? null) === 'payment'
                && ($data['client_reference_id'] ?? null) === (string) $user->getKey()
                && ($data['allow_promotion_codes'] ?? null) === 'true'
                && data_get($data, 'line_items.0.price') === 'price_pack_25_123'
                && data_get($data, 'metadata.offer') === 'pack_25'
                && data_get($data, 'metadata.offer_type') === 'credit_pack'
                && data_get($data, 'metadata.credits') === '25';
        });
    }

    /**
     * Existing Stripe customers should be able to open the billing portal.
     */
    public function test_billing_portal_route_redirects_to_stripe_customer_portal(): void
    {
        Config::set('services.stripe.secret', 'sk_test_123');

        $user = User::factory()->create([
            'stripe_customer_id' => 'cus_123',
        ]);

        Http::fake([
            'https://api.stripe.com/v1/billing_portal/sessions' => Http::response([
                'url' => 'https://billing.stripe.com/p/session/test_portal',
            ]),
        ]);

        $response = $this->actingAs($user)->post(route('billing.portal'));

        $response->assertRedirect('https://billing.stripe.com/p/session/test_portal');
    }

    /**
     * Stripe webhooks should keep the local subscription state in sync.
     */
    public function test_webhook_updates_the_local_subscription_state(): void
    {
        Config::set('services.stripe.webhook_secret', 'whsec_test_123');

        $user = User::factory()->create([
            'generated_posts_count' => 5,
        ]);

        $payload = [
            'type' => 'customer.subscription.updated',
            'data' => [
                'object' => [
                    'id' => 'sub_123',
                    'customer' => 'cus_123',
                    'status' => 'active',
                    'current_period_end' => now()->addMonth()->timestamp,
                    'metadata' => [
                        'user_id' => (string) $user->getKey(),
                        'plan' => 'annual',
                    ],
                    'items' => [
                        'data' => [
                            [
                                'price' => [
                                    'id' => 'price_annual_123',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = $this->stripeSignature($body, 'whsec_test_123');

        $response = $this->call(
            'POST',
            route('billing.webhook'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => $signature,
            ],
            $body,
        );

        $response->assertOk();

        $user->refresh();

        $this->assertSame('cus_123', $user->stripe_customer_id);
        $this->assertSame('sub_123', $user->stripe_subscription_id);
        $this->assertSame('price_annual_123', $user->stripe_price_id);
        $this->assertSame('active', $user->subscription_status);
        $this->assertSame('annual', $user->subscription_plan);
        $this->assertTrue($user->hasActiveSubscription());
    }

    /**
     * Credit-pack webhooks should grant credits once even if the same session is received twice.
     */
    public function test_credit_pack_webhook_grants_credits_once(): void
    {
        Config::set('services.stripe.webhook_secret', 'whsec_test_123');
        Config::set('billing.credit_packs.pack_25.stripe_price_id', 'price_pack_25_123');

        $user = User::factory()->create([
            'generated_posts_count' => 5,
            'credit_balance' => 0,
        ]);

        $payload = [
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_pack_25',
                    'mode' => 'payment',
                    'customer' => 'cus_pack_123',
                    'payment_intent' => 'pi_pack_123',
                    'payment_status' => 'paid',
                    'amount_total' => 1200,
                    'currency' => 'gbp',
                    'metadata' => [
                        'user_id' => (string) $user->getKey(),
                        'offer' => 'pack_25',
                        'offer_type' => 'credit_pack',
                        'credits' => '25',
                    ],
                ],
            ],
        ];

        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = $this->stripeSignature($body, 'whsec_test_123');

        $this->call(
            'POST',
            route('billing.webhook'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => $signature,
            ],
            $body,
        )->assertOk();

        $this->call(
            'POST',
            route('billing.webhook'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => $signature,
            ],
            $body,
        )->assertOk();

        $user->refresh();

        $this->assertSame(25, $user->creditBalance());
        $this->assertDatabaseHas('credit_purchases', [
            'user_id' => $user->getKey(),
            'offer_key' => 'pack_25',
            'credits_granted' => 25,
            'stripe_checkout_session_id' => 'cs_test_pack_25',
        ]);
        $this->assertDatabaseCount('credit_purchases', 1);
    }

    /**
     * Build a Stripe-Signature header for webhook feature tests.
     */
    protected function stripeSignature(string $payload, string $secret): string
    {
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        return "t={$timestamp},v1={$signature}";
    }
}
