<?php
// app/Http/Controllers/MoneyRequestController.php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\User;
use App\Models\MoneyRequest;
use App\Helpers\MoneyHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Label\Label;
use Endroid\QrCode\Label\Font\OpenSans;

class MoneyRequestController extends Controller
{
    /**
     * Show the request money page with QR code containing app logo
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        $user = auth()->user();
        
        // Get user's default wallet for currency
        $defaultWallet = $user->wallets()->where('is_default', true)->with('currency')->first();
        $currency = $defaultWallet?->currency;
        
        // Generate unique request ID for this session
        $requestId = Str::random(32);
        
        // Generate QR code data (user ID + request ID)
        $qrData = json_encode([
            'user_id' => $user->id,
            'request_id' => $requestId,
            'user_name' => $user->name,
            'user_email' => $user->email,
            'timestamp' => now()->timestamp,
            'type' => 'money_request',
        ]);
        
        // Generate QR code with app logo in the center
        $qrCode = $this->generateQRCodeWithLogo($qrData);
        
        return Inertia::render('moneyRequest/Index', [
            'qrCode' => $qrCode,
            'requestId' => $requestId,
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
     * Generate a professional QR code with app logo in the center
     *
     * @param string $data The data to encode in the QR code
     * @return string Base64 encoded PNG image
     */
    private function generateQRCodeWithLogo($data)
    {
        try {
            // Create QR code with all options in constructor
            $qrCode = new QrCode(
                data: $data,
                errorCorrectionLevel: ErrorCorrectionLevel::High,
                size: 400,
                margin: 10,
            );
            
            // Create writer
            $writer = new PngWriter();
            
            // Path to your app logo
            $logoPath = Storage::disk('public')->path('logos/app-logo.png');
            
            // Create result
            $result = $writer->write($qrCode);
            
            // Add logo if it exists
            if (file_exists($logoPath)) {
                $logo = Logo::create($logoPath)
                    ->setResizeToWidth(80);
                
                // Re-write with logo
                $result = $writer->write($qrCode, $logo);
            }
            
            // Return as base64 for inline display
            return 'data:image/png;base64,' . base64_encode($result->getString());
            
        } catch (\Exception $e) {
            \Log::error('QR Code generation failed: ' . $e->getMessage());
            return $this->generateSimpleQRCode($data);
        }
    }
    
    /**
     * Generate a simple QR code without logo (fallback)
     *
     * @param string $data
     * @return string
     */
    private function generateSimpleQRCode($data)
    {
        try {
            $qrCode = new QrCode(
                data: $data,
                size: 400,
                margin: 10,
            );
            
            $writer = new PngWriter();
            $result = $writer->write($qrCode);
            
            return 'data:image/png;base64,' . base64_encode($result->getString());
        } catch (\Exception $e) {
            // Ultimate fallback - return a placeholder
            return 'data:image/svg+xml,' . urlencode('<svg width="400" height="400" xmlns="http://www.w3.org/2000/svg"><rect width="400" height="400" fill="#333"/><text x="200" y="200" text-anchor="middle" fill="#fff">QR Code</text></svg>');
        }
    }
    
    /**
     * Generate a downloadable QR code for a specific money request
     *
     * @param string $token
     * @return \Illuminate\Http\Response
     */
    public function generateRequestQRCode($token)
    {
        $moneyRequest = MoneyRequest::where('request_token', $token)
            ->with('user')
            ->firstOrFail();
        
        $qrData = json_encode([
            'type' => 'money_request',
            'request_token' => $token,
            'amount' => $moneyRequest->amount,
            'currency' => $moneyRequest->currency_code,
            'description' => $moneyRequest->description,
            'requester_name' => $moneyRequest->user->name,
        ]);
        
        try {
            $qrCode = new QrCode(
                data: $qrData,
                size: 400,
                margin: 10,
                errorCorrectionLevel: ErrorCorrectionLevel::High,
            );
            
            $writer = new PngWriter();
            
            // Add logo if exists
            $logoPath = Storage::disk('public')->path('logos/app-logo.png');
            if (file_exists($logoPath)) {
                $logo = Logo::create($logoPath)->setResizeToWidth(80);
                $result = $writer->write($qrCode, $logo);
            } else {
                $result = $writer->write($qrCode);
            }
            
            return response($result->getString())
                ->header('Content-Type', 'image/png')
                ->header('Content-Disposition', 'attachment; filename="money-request-qr.png"');
                
        } catch (\Exception $e) {
            \Log::error('QR Code generation failed: ' . $e->getMessage());
            
            // Fallback to simple QR without logo
            $qrCode = new QrCode(data: $qrData, size: 400);
            $writer = new PngWriter();
            $result = $writer->write($qrCode);
            
            return response($result->getString())
                ->header('Content-Type', 'image/png')
                ->header('Content-Disposition', 'attachment; filename="money-request-qr.png"');
        }
    }
    
    /**
     * Create a money request link
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
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
        
        return response()->json([
            'success' => true,
            'request_url' => route('money-request.pay', $moneyRequest->request_token),
            'request_token' => $moneyRequest->request_token,
            'qr_code_url' => route('money-request.qr-code', $moneyRequest->request_token),
        ]);
    }
    
    /**
     * Show payment page for a money request
     *
     * @param string $token
     * @return \Inertia\Response
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
     *
     * @param Request $request
     * @param string $token
     * @return \Illuminate\Http\RedirectResponse
     */
    public function processPayment(Request $request, $token)
    {
        $moneyRequest = MoneyRequest::where('request_token', $token)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->firstOrFail();
        
        $user = auth()->user();
        
        // Prevent paying your own request
        if ($user->id === $moneyRequest->user_id) {
            return back()->with('error', 'You cannot pay your own money request.');
        }
        
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'wallet_id' => 'required|exists:wallets,id',
        ]);
        
        $amountInSmallestUnit = MoneyHelper::toSmallestUnit($validated['amount'], $moneyRequest->currency_code);
        
        // Validate amount matches the request
        if ($amountInSmallestUnit != $moneyRequest->amount) {
            return back()->with('error', 'Amount does not match the requested amount.');
        }
        
        $sourceWallet = $user->wallets()->findOrFail($validated['wallet_id']);
        
        // Validate currency matches
        if ($sourceWallet->currency_code !== $moneyRequest->currency_code) {
            return back()->with('error', 'Wallet currency must match the request currency.');
        }
        
        // Check if user has sufficient balance
        if ($sourceWallet->balance < $amountInSmallestUnit) {
            return back()->with('error', 'Insufficient balance.');
        }
        
        DB::beginTransaction();
        
        try {
            // Deduct from payer's wallet
            $sourceWallet->decreaseBalance($amountInSmallestUnit);
            
            // Add to requester's wallet
            $requesterWallet = $moneyRequest->wallet;
            $requesterWallet->increaseBalance($amountInSmallestUnit);
            
            // Update money request status
            $moneyRequest->update([
                'status' => 'completed',
                'paid_at' => now(),
                'paid_by_user_id' => $user->id,
            ]);
            
            // Create transaction record for payer
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
                    'request_description' => $moneyRequest->description,
                    'requester_name' => $moneyRequest->user->name,
                ],
            ]);
            
            // Create transaction record for requester
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
                    'request_description' => $moneyRequest->description,
                    'payer_name' => $user->name,
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
     * Get currency symbol from currency code
     *
     * @param string $code
     * @return string
     */
    private function getCurrencySymbol($code)
    {
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
            'BRL' => 'R$',
            'CAD' => 'C$',
            'AUD' => 'A$',
            'CHF' => 'CHF',
            'CNY' => '¥',
            'INR' => '₹',
        ];
        return $symbols[$code] ?? '$';
    }
}