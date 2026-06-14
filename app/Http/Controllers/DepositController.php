<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use App\Models\Currency;
use App\Services\Common\RateLimitService;
use App\Services\Common\LockService;
use App\Services\Deposit\PendingDepositService;
use App\Services\Deposit\StripePaymentService;
use App\Services\Deposit\PayPalPaymentService;
use App\Services\Deposit\WebhookService;
use Illuminate\Support\Facades\Log;

class DepositController extends Controller
{
    protected RateLimitService $rateLimitService;
    protected LockService $lockService;
    protected PendingDepositService $pendingDepositService;
    protected StripePaymentService $stripePaymentService;
    protected PayPalPaymentService $payPalPaymentService;
    protected WebhookService $webhookService;

    public function __construct(
        RateLimitService $rateLimitService,
        LockService $lockService,
        PendingDepositService $pendingDepositService,
        StripePaymentService $stripePaymentService,
        PayPalPaymentService $payPalPaymentService,
        WebhookService $webhookService
    ) {
        $this->rateLimitService = $rateLimitService;
        $this->lockService = $lockService;
        $this->pendingDepositService = $pendingDepositService;
        $this->stripePaymentService = $stripePaymentService;
        $this->payPalPaymentService = $payPalPaymentService;
        $this->webhookService = $webhookService;
    }

    // ==================== PAGE ROUTES ====================

    public function index()
    {
        return Inertia::render('deposits/Index');
    }

    public function card()
    {
        return Inertia::render('deposits/Card');
    }

    public function authorizedAgent()
    {
        return Inertia::render('deposits/AuthorizedAgent');
    }

    public function paypal()
    {
        return Inertia::render('deposits/PayPal');
    }

    public function success(Request $request)
    {
        $amount = $request->get('amount', 0);
        $sessionId = $request->get('session_id');

        if ($amount <= 0) {
            return redirect()->route('dashboard')->with('error', 'Invalid deposit information');
        }

        $user = $request->user();
        $defaultWallet = $user->wallets()->where('is_default', true)->first();
        $currency = $defaultWallet?->currency ?? Currency::where('code', 'USD')->first();
        $formattedAmount = $currency->format($amount);

        return Inertia::render('deposits/Success', [
            'amount' => $amount,
            'formatted_amount' => $formattedAmount,
            'session_id' => $sessionId,
        ]);
    }

    public function cancel(Request $request)
    {
        return redirect()->route('deposits.card')->with('error', 'Deposit was cancelled. Please try again.');
    }

    // ==================== STRIPE METHODS ====================

    public function createPaymentIntent(Request $request)
    {
        try {
            $this->rateLimitService->check($request->user());
            $this->pendingDepositService->check($request->user());

            $request->validate([
                'amount' => 'required|numeric|min:10|max:5000',
                'currency' => 'sometimes|string|size:3',
            ]);

            $user = $request->user();
            $amount = $request->amount;
            $currency = Currency::where('code', $request->currency ?? 'USD')->first() ?? Currency::where('code', 'USD')->first();

            $lock = $this->lockService->acquire($user);

            try {
                $result = $this->stripePaymentService->createPaymentIntent($user, $amount, $currency);
                $this->lockService->release($lock);
                return response()->json($result);
            } catch (\Exception $e) {
                $this->lockService->release($lock);
                throw $e;
            }
        } catch (\Exception $e) {
            $status = match ($e->getMessage()) {
                'Please wait a moment before trying again.' => 429,
                'You already have a pending deposit. Please wait for it to complete.' => 409,
                default => 500,
            };

            return response()->json(['message' => $e->getMessage()], $status);
        }
    }

    public function confirmPayment(Request $request)
    {
        try {
            $request->validate([
                'payment_intent_id' => 'required|string',
                'amount' => 'required|numeric|min:10|max:5000',
                'currency' => 'sometimes|string|size:3',
            ]);

            $user = $request->user();
            $paymentIntentId = $request->payment_intent_id;
            $amount = $request->amount;
            $currency = Currency::where('code', $request->currency ?? 'USD')->first() ?? Currency::where('code', 'USD')->first();

            // Check if already processed (idempotency)
            $existingTransaction = \App\Models\Transaction::where('reference', $paymentIntentId)
                ->whereIn('status', ['completed', 'refunded'])
                ->first();

            if ($existingTransaction) {
                return response()->json([
                    'success' => true,
                    'already_processed' => true,
                    'message' => 'This payment has already been processed.',
                ]);
            }

            $lock = $this->lockService->acquire($user);

            try {
                $result = $this->stripePaymentService->confirmPayment($user, $paymentIntentId, $amount, $currency);
                $this->lockService->release($lock);
                return response()->json($result);
            } catch (\Exception $e) {
                $this->lockService->release($lock);
                throw $e;
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function rollbackPayment(Request $request)
    {
        try {
            $request->validate(['payment_intent_id' => 'required|string']);

            $user = $request->user();
            $paymentIntentId = $request->payment_intent_id;

            $lock = $this->lockService->acquire($user);

            try {
                $result = $this->stripePaymentService->rollbackPayment($user, $paymentIntentId);
                $this->lockService->release($lock);
                return response()->json($result);
            } catch (\Exception $e) {
                $this->lockService->release($lock);
                throw $e;
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function handleWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = config('cashier.webhook.secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (SignatureVerificationException $e) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;

            $userId = $session['metadata']['user_id'] ?? null;
            $amount = $session['metadata']['amount'] ?? 0;
            $sessionId = $session['id'];

            if ($userId && $amount > 0) {
                $this->webhookService->processSuccessfulDeposit($userId, $amount, $sessionId);
            }
        }

        return response()->json(['status' => 'success']);
    }

    // ==================== PAYPAL METHODS ====================

    public function createPayPalOrder(Request $request)
    {
        try {
            $request->validate(['amount' => 'required|numeric|min:10|max:5000']);

            $this->rateLimitService->check($request->user());
            $this->pendingDepositService->check($request->user());

            $user = $request->user();
            $amount = $request->amount;
            $defaultWallet = $user->wallets()->where('is_default', true)->first();
            $currency = $defaultWallet?->currency ?? Currency::where('code', 'USD')->first();

            $result = $this->payPalPaymentService->createOrder($user, $amount, $currency);

            return response()->json(['approval_url' => $result['approval_url']]);
        } catch (\Exception $e) {
            $status = match ($e->getMessage()) {
                'Please wait a moment before trying again.' => 429,
                'You already have a pending deposit. Please wait for it to complete.' => 409,
                default => 500,
            };

            return response()->json(['error' => $e->getMessage()], $status);
        }
    }

    public function handlePayPalSuccess(Request $request)
    {
        $token = $request->query('token');
        $payerId = $request->query('PayerID');

        if (!$token) {
            return redirect()->route('deposits.paypal.create-order')->with('error', 'Invalid payment token');
        }

        try {
            $result = $this->payPalPaymentService->captureOrder($token, $payerId);
            $orderId = $result['id'];

            $processed = $this->payPalPaymentService->processCapturedOrder($orderId, $payerId);

            return Inertia::render('deposits/Success', [
                'type' => 'deposit',
                'amount' => $processed['amount'],
                'currency' => $processed['currency']->code,
                'currency_symbol' => $processed['currency']->symbol,
                'reference' => $processed['transaction']->reference,
                'fee' => $processed['transaction']->metadata['fee_amount'] ?? 0,
                'date' => now()->toISOString(),
                'status' => 'Completed',
            ]);
        } catch (\Exception $e) {
            Log::error('PayPal success handling failed', [
                'error' => $e->getMessage(),
                'token' => $token,
            ]);
            return redirect()->route('deposits.paypal.create-order')->with('error', 'Payment processing failed');
        }
    }

    public function handlePayPalCancel(Request $request)
    {
        return redirect()->route('deposits.paypal.create-order')->with('error', 'Payment was cancelled');
    }
}
