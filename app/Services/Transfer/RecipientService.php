<?php

namespace App\Services\Transfer;

use App\Models\User;
use App\Models\Transaction;

class RecipientService
{
    /**
     * Find recipient by email
     */
    public function findByEmail(string $email, User $currentUser): array
    {
        if ($currentUser->email === $email) {
            throw new \Exception('You cannot send money to yourself');
        }

        $recipient = User::where('email', $email)->first();

        if (!$recipient) {
            throw new \Exception('User not found. Please check the email address.');
        }

        $recipientWallets = $recipient->wallets()->with('currency')->get()->map(function ($wallet) {
            return [
                'id' => $wallet->id,
                'currency_code' => $wallet->currency->code,
                'currency_symbol' => $wallet->currency->symbol,
                'is_default' => $wallet->is_default,
            ];
        });

        return [
            'id' => $recipient->id,
            'name' => $recipient->name,
            'email' => $recipient->email,
            'wallets' => $recipientWallets,
            'default_currency' => $recipientWallets->firstWhere('is_default', true) ?? $recipientWallets->first(),
        ];
    }

    /**
     * Get recent recipients for a user
     */
    public function getRecentRecipients(User $user): array
    {
        $sentTransfers = Transaction::where('user_id', $user->id)
            ->where('type', 'transfer')
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        $recipients = [];
        foreach ($sentTransfers as $transfer) {
            $metadata = $transfer->metadata;
            if ($metadata && isset($metadata['recipient_id'])) {
                $recipientId = $metadata['recipient_id'];
                if (!isset($recipients[$recipientId])) {
                    $recipient = User::find($recipientId);
                    if ($recipient) {
                        $recipients[$recipientId] = [
                            'id' => $recipient->id,
                            'name' => $recipient->name,
                            'email' => $recipient->email,
                            'avatar' => strtoupper(substr($recipient->name, 0, 2)),
                        ];
                    }
                }
            }
        }

        return array_values(array_slice($recipients, 0, 5));
    }
}