<?php
// app/Http/Controllers/MoneyRequestController.php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\User;
use App\Models\MoneyRequest;
use App\Helpers\MoneyHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MoneyRequestController extends Controller
{
    /**
     * Show the request money page with QR code containing user's email
     */
    public function index()
    {
        $user = auth()->user();
        
        $defaultWallet = $user->wallets()->where('is_default', true)->with('currency')->first();
        $currency = $defaultWallet?->currency;
        
        // SIMPLE EMAIL - Just the email address
        $qrData = $user->email;
        
        // Generate QR code using free API (no package needed!)
        $qrCode = $this->generateQRCode($qrData);
        
        return Inertia::render('moneyRequest/Index', [
            'qrCode' => $qrCode,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'currency' => $currency ? [
                'code' => $currency->code,
                'symbol' => $currency->symbol,
            ] : [
                'code' => 'USD',
                'symbol' => '$',
            ],
        ]);
    }
    
    /**
     * Generate QR code using free QR code API (no package needed!)
     * This works instantly without any composer dependencies
     */
    private function generateQRCode($data)
    {
        // Encode the data for URL
        $encodedData = urlencode($data);
        
        // Use free QR code API (QR Server)
        // Returns a PNG image directly
        $qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data={$encodedData}";
        
        // Return the URL - Vue will load the image
        return $qrCodeUrl;
    }
    
    /**
     * Alternative: Generate QR code with logo (using API with logo support)
     */
    private function generateQRCodeWithLogo($data)
    {
        // For now, use simple QR code
        return $this->generateQRCode($data);
    }
    
    /**
     * Create a money request (for specific amount requests)
     */
    public function createRequest(Request $request)
    {
        $user = auth()->user();
        
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:10000',
            'description' => 'nullable|string|max:255',
        ]);
        
        $defaultWallet = $user->wallets()->where('is_default', true)->with('currency')->first();
        
        $moneyRequest = MoneyRequest::create([
            'user_id' => $user->id,
            'wallet_id' => $defaultWallet->id,
            'amount' => MoneyHelper::toSmallestUnit($validated['amount'], $defaultWallet->currency->code),
            'currency_code' => $defaultWallet->currency->code,
            'description' => $validated['description'],
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
            'request_token' => Str::random(64),
        ]);
        
        // Create QR code with email
        $qrData = $user->email;
        $qrCode = $this->generateQRCode($qrData);
        
        return response()->json([
            'success' => true,
            'request_url' => route('money-request.pay', $moneyRequest->request_token),
            'request_token' => $moneyRequest->request_token,
            'qr_code' => $qrCode,
        ]);
    }
    
    /**
     * Show payment page for a money request
     */
    public function showPayPage($token)
    {
        $moneyRequest = MoneyRequest::where('request_token', $token)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->with('user')
            ->firstOrFail();
        
        $amount = MoneyHelper::fromSmallestUnit($moneyRequest->amount, $moneyRequest->currency_code);
        
        return Inertia::render('moneyRequest/Pay', [
            'request' => [
                'id' => $moneyRequest->id,
                'request_token' => $moneyRequest->request_token,
                'amount' => $amount,
                'formatted_amount' => MoneyHelper::format($moneyRequest->amount, $moneyRequest->currency_code),
                'currency_code' => $moneyRequest->currency_code,
                'currency_symbol' => $this->getCurrencySymbol($moneyRequest->currency_code),
                'description' => $moneyRequest->description,
                'expires_at' => $moneyRequest->expires_at,
                'requester' => [
                    'id' => $moneyRequest->user->id,
                    'name' => $moneyRequest->user->name,
                    'email' => $moneyRequest->user->email,
                ],
            ],
        ]);
    }
    
    /**
     * Process payment for a money request
     */
    public function processPayment(Request $request, $token)
    {
        $moneyRequest = MoneyRequest::where('request_token', $token)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->firstOrFail();
        
        $user = auth()->user();
        
        if ($user->id === $moneyRequest->user_id) {
            return back()->with('error', 'You cannot pay your own money request.');
        }
        
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'wallet_id' => 'required|exists:wallets,id',
        ]);
        
        $amountInSmallestUnit = MoneyHelper::toSmallestUnit($validated['amount'], $moneyRequest->currency_code);
        
        if ($amountInSmallestUnit != $moneyRequest->amount) {
            return back()->with('error', 'Amount does not match the requested amount.');
        }
        
        $sourceWallet = $user->wallets()->findOrFail($validated['wallet_id']);
        
        if ($sourceWallet->currency_code !== $moneyRequest->currency_code) {
            return back()->with('error', 'Wallet currency must match the request currency.');
        }
        
        if ($sourceWallet->balance < $amountInSmallestUnit) {
            return back()->with('error', 'Insufficient balance.');
        }
        
        DB::beginTransaction();
        
        try {
            $sourceWallet->decreaseBalance($amountInSmallestUnit);
            $requesterWallet = $moneyRequest->wallet;
            $requesterWallet->increaseBalance($amountInSmallestUnit);
            
            $moneyRequest->update([
                'status' => 'completed',
                'paid_at' => now(),
                'paid_by_user_id' => $user->id,
            ]);
            
            \App\Models\Transaction::create([
                'user_id' => $user->id,
                'source_wallet_id' => $sourceWallet->id,
                'destination_wallet_id' => $requesterWallet->id,
                'type' => 'money_request_payment',
                'amount' => $amountInSmallestUnit,
                'status' => 'completed',
                'description' => "Payment to {$moneyRequest->user->name} for money request",
                'completed_at' => now(),
                'metadata' => [
                    'request_id' => $moneyRequest->id,
                ],
            ]);
            
            \App\Models\Transaction::create([
                'user_id' => $moneyRequest->user_id,
                'source_wallet_id' => $sourceWallet->id,
                'destination_wallet_id' => $requesterWallet->id,
                'type' => 'money_request_received',
                'amount' => $amountInSmallestUnit,
                'status' => 'completed',
                'description' => "Payment received from {$user->name} for money request",
                'completed_at' => now(),
                'metadata' => [
                    'request_id' => $moneyRequest->id,
                ],
            ]);
            
            DB::commit();
            
            return redirect()->route('dashboard')->with('success', 'Payment sent successfully!');
            
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Payment failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Generate a downloadable QR code for a specific money request
     */
    public function generateRequestQRCode($token)
    {
        $moneyRequest = MoneyRequest::where('request_token', $token)->firstOrFail();
        $qrData = $moneyRequest->user->email;
        $encodedData = urlencode($qrData);
        
        // Redirect to QR code API for download
        $qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data={$encodedData}";
        
        // Fetch the image and return as download
        $imageContent = file_get_contents($qrCodeUrl);
        
        return response($imageContent)
            ->header('Content-Type', 'image/png')
            ->header('Content-Disposition', 'attachment; filename="payment-qr.png"');
    }
    
    private function getCurrencySymbol($code)
    {
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
            'BRL' => 'R$',
        ];
        return $symbols[$code] ?? '$';
    }
}