<?php

namespace App\Services\Transfer;

use App\Models\User;
use App\Models\Wallet;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransferExecutionService
{
    /**
     * Execute the transfer transaction
     */
    public function execute(array $quote, User $sender): array
    {
        // Lock and refresh wallets
        $sourceWallet = Wallet::where('id', $quote['source_wallet_id'])
            ->lockForUpdate()
            ->first();

        $totalRequired = $quote['amount_in_smallest_unit'] + $quote['fee_in_smallest_unit'];
        if ($sourceWallet->balance < $totalRequired) {
            throw new \Exception('Insufficient balance. Please refresh and try again.');
        }

        $recipientWallet = Wallet::where('id', $quote['recipient_wallet_id'])
            ->lockForUpdate()
            ->first();

        if (!$recipientWallet) {
            throw new \Exception('Recipient wallet not found');
        }

        $recipient = User::find($quote['recipient_id']);
        if (!$recipient) {
            throw new \Exception('Recipient not found');
        }

        $baseReference = 'trans_' . uniqid() . '_' . time();

        // Debit sender
        $sourceWallet->decreaseBalance($quote['amount_in_smallest_unit']);
        $sourceWallet->decreaseBalance($quote['fee_in_smallest_unit']);

        // Credit recipient
        $recipientWallet->increaseBalance($quote['converted_amount_in_smallest_unit']);

        // Create sender transaction record
        Transaction::create([
            'user_id' => $sender->id,
            'source_wallet_id' => $sourceWallet->id,
            'destination_wallet_id' => $recipientWallet->id,
            'type' => 'transfer',
            'amount' => $quote['amount_in_smallest_unit'],
            'exchange_amount' => $quote['converted_amount_in_smallest_unit'],
            'exchange_rate' => $quote['rate'],
            'fee_amount' => $quote['fee_in_smallest_unit'],
            'status' => 'completed',
            'reference' => $baseReference . '_sent',
            'description' => "Transfer sent to {$recipient->name} ({$recipient->email})",
            'completed_at' => now(),
            'metadata' => [
                'recipient_id' => $recipient->id,
                'recipient_name' => $recipient->name,
                'recipient_email' => $recipient->email,
                'recipient_currency' => $quote['target_currency'],
                'recipient_amount' => $quote['converted_amount'],
                'transfer_fee' => $quote['fee'],
                'exchange_rate_used' => $quote['rate'],
            ],
        ]);

        // Create recipient transaction record
        Transaction::create([
            'user_id' => $recipient->id,
            'source_wallet_id' => $sourceWallet->id,
            'destination_wallet_id' => $recipientWallet->id,
            'type' => 'transfer',
            'amount' => $quote['converted_amount_in_smallest_unit'],
            'exchange_amount' => $quote['amount_in_smallest_unit'],
            'exchange_rate' => $quote['rate'],
            'fee_amount' => 0,
            'status' => 'completed',
            'reference' => $baseReference . '_received',
            'description' => "Transfer received from {$sender->name} ({$sender->email})",
            'completed_at' => now(),
            'metadata' => [
                'sender_id' => $sender->id,
                'sender_name' => $sender->name,
                'sender_email' => $sender->email,
                'sender_currency' => $quote['source_currency'],
                'sender_amount' => $quote['amount'],
                'exchange_rate_used' => $quote['rate'],
            ],
        ]);

        Log::info('Transfer completed successfully', [
            'from_user' => $sender->id,
            'to_user' => $recipient->id,
            'amount' => $quote['amount'],
            'currency' => $quote['source_currency'],
            'reference' => $baseReference,
        ]);

        return [
            'reference' => $baseReference,
            'amount' => $quote['amount'],
            'currency' => $quote['source_currency'],
            'recipient_name' => $recipient->name,
            'recipient_email' => $recipient->email,
            'converted_amount' => $quote['converted_amount'],
            'target_currency' => $quote['target_currency'],
            'fee' => $quote['fee'],
        ];
    }
}