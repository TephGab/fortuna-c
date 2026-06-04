<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Cashier\Cashier;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;

class DepositController extends Controller
{
    /**
     * Display deposit options page
     */
    public function index()
    {
        return Inertia::render('deposits/Index');
    }

    /**
     * Display card deposit page
     */
    public function card()
    {
        return Inertia::render('deposits/Card');
    }

    /**
     * Create Stripe Checkout Session for card deposit
     */
    public function createCheckout(Request $request)
    {
        // Validate amount
        $request->validate([
            'amount' => 'required|numeric|min:10|max:5000',
        ]);

        $user = $request->user();
        $amount = $request->amount;

        // Create a Stripe Checkout session using Cashier
        $checkout = $user->checkoutCharge(
            $amount,                          // Amount in dollars
            'Wallet Deposit',                 // Product name
            1,                                // Quantity
            [
                'success_url' => route('deposits.success') . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('deposits.cancel'),
                'metadata' => [
                    'user_id' => $user->id,
                    'amount' => $amount,
                    'type' => 'deposit'
                ],
            ]
        );

        // Return the checkout URL to redirect to Stripe
        return response()->json([
            'url' => $checkout->url
        ]);
    }

    /**
     * Handle successful deposit
     */
    public function success(Request $request)
    {
        $sessionId = $request->get('session_id');

        if (!$sessionId) {
            return redirect()->route('dashboard')
                ->with('error', 'Invalid payment session');
        }

        try {
            // Retrieve the session from Stripe
            $session = Cashier::stripe()->checkout->sessions->retrieve($sessionId);

            // Get metadata
            $amount = $session['metadata']['amount'] ?? 0;
            $userId = $session['metadata']['user_id'] ?? null;

            // Return success page
            return Inertia::render('deposits/Success', [
                'amount' => $amount,
                'sessionId' => $sessionId
            ]);
        } catch (\Exception $e) {
            return redirect()->route('dashboard')
                ->with('error', 'Payment verification failed');
        }
    }

    /**
     * Handle cancelled deposit
     */
    public function cancel(Request $request)
    {
        return redirect()->route('deposits.card')
            ->with('error', 'Deposit was cancelled');
    }

    /**
     * Handle Stripe Webhook
     */
    public function handleWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = config('cashier.webhook.secret');

        // Verify webhook signature
        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (SignatureVerificationException $e) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        // Handle checkout.session.completed event
        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            
            // Get metadata
            $userId = $session['metadata']['user_id'] ?? null;
            $amount = $session['metadata']['amount'] ?? 0;
            $sessionId = $session['id'];

            // Process the deposit if user exists and amount is valid
            if ($userId && $amount > 0) {
                $this->processSuccessfulDeposit($userId, $amount, $sessionId);
            }
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Process successful deposit (update wallet and create transaction)
     */
    private function processSuccessfulDeposit($userId, $amount, $sessionId)
    {
        Log::info("Processing successful deposit for user_id: $userId, amount: $amount, session_id: $sessionId");
        // $user = User::find($userId);

        // if (!$user) {
        //     return;
        // }

        // // Get or create wallet for user
        // $wallet = $user->wallet;
        // if (!$wallet) {
        //     $wallet = Wallet::create([
        //         'user_id' => $user->id,
        //         'balance' => 0
        //     ]);
        // }

        // // Update wallet balance
        // $wallet->incrementBalance($amount);

        // // Create transaction record
        // Transaction::create([
        //     'user_id' => $user->id,
        //     'type' => 'deposit',
        //     'amount' => $amount,
        //     'status' => 'completed',
        //     'reference' => $sessionId,
        //     'payment_method' => 'stripe',
        //     'description' => 'Deposit via Credit/Debit Card'
        // ]);
    }
}