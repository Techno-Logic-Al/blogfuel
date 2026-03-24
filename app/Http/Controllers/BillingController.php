<?php

namespace App\Http\Controllers;

use App\Services\StripeBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class BillingController extends Controller
{
    /**
     * Redirect the user to Stripe Checkout for a selected plan.
     */
    public function checkout(Request $request, string $plan, StripeBillingService $billing): RedirectResponse
    {
        abort_unless(
            is_array(config("billing.plans.{$plan}"))
                || is_array(config("billing.credit_packs.{$plan}")),
            404
        );

        $user = $request->user();

        if ($user?->hasActiveSubscription()) {
            return redirect()
                ->route('posts.index')
                ->with('status', 'A paid plan is already active on this account.');
        }

        try {
            $checkoutUrl = $billing->createCheckoutSession($user, $plan);
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('posts.index')
                ->withErrors(['billing' => $exception->getMessage()]);
        }

        return redirect()->away($checkoutUrl);
    }

    /**
     * Redirect the user to the Stripe billing portal.
     */
    public function portal(Request $request, StripeBillingService $billing): RedirectResponse
    {
        try {
            $portalUrl = $billing->createBillingPortalSession($request->user());
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('posts.index')
                ->withErrors(['billing' => $exception->getMessage()]);
        }

        return redirect()->away($portalUrl);
    }

    /**
     * Return from Stripe Checkout and sync access immediately when possible.
     */
    public function success(Request $request, StripeBillingService $billing): RedirectResponse
    {
        $message = 'Stripe checkout completed. Your access should unlock shortly.';
        $sessionId = trim((string) $request->query('session_id'));

        if ($sessionId !== '') {
            try {
                $result = $billing->syncCheckoutSessionForUser($request->user(), $sessionId);

                $message = match ($result['type'] ?? null) {
                    'subscription' => 'Subscription activated. Unlimited article generation is now unlocked.',
                    'credit_pack' => max(0, (int) ($result['credits_added'] ?? 0)) > 0
                        ? sprintf(
                            '%d article credits added to your account.',
                            max(0, (int) ($result['credits_added'] ?? 0))
                        )
                        : 'Credit-pack payment completed. Your article credits should appear shortly.',
                    default => $message,
                };
            } catch (RuntimeException) {
                // Webhooks can still complete the sync if this redirect arrives first.
            }
        }

        return redirect()
            ->route('posts.index')
            ->with('status', $message);
    }

    /**
     * Receive Stripe webhook events that keep subscription access in sync.
     */
    public function webhook(Request $request, StripeBillingService $billing): JsonResponse
    {
        try {
            $billing->handleWebhook(
                $request->getContent(),
                (string) $request->header('Stripe-Signature')
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 400);
        }

        return response()->json(['received' => true]);
    }
}
