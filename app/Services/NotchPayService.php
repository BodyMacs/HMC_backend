<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use NotchPay\NotchPay;
use NotchPay\Payment;

class NotchPayService
{
    public function __construct()
    {
        NotchPay::setApiKey(env('NOTCHPAY_API_KEY'));
        NotchPay::setPrivateKey(env('NOTCHPAY_PRIVATE_KEY'));
    }

    /**
     * Initializes a NotchPay transaction
     */
    public function initializePayment(array $data)
    {
        try {
            $response = Payment::initialize([
                'amount' => $data['amount'],
                'email' => $data['email'],
                'currency' => $data['currency'] ?? 'XAF',
                'reference' => $data['reference'],
                'callback' => env('NOTCHPAY_CALLBACK_URL', url('/api/notchpay/callback')),
                'description' => $data['description'] ?? 'Paiement',
                'metadata' => $data['metadata'] ?? [],
            ]);

            return $response;
        } catch (\Exception $e) {
            Log::error('NotchPay Initialize Exception: ' . $e->getMessage());

            throw $e;
        }
    }

    /**
     * Verifies a NotchPay transaction
     */
    public function verifyPayment($reference)
    {
        try {
            return Payment::verify($reference);
        } catch (\Exception $e) {
            Log::error('NotchPay Verify Exception: ' . $e->getMessage());

            throw $e;
        }
    }

    /**
     * Check status and fulfill if successful
     */
    public function checkAndFulfillTransaction($reference): string
    {
        $transaction = \App\Models\Transaction::where('reference', $reference)->first();
        if (!$transaction) return 'not_found';
        if ($transaction->status === 'successful') return 'already_successful';

        try {
            $payment = $this->verifyPayment($reference);

            // Status can be in $payment->status OR $payment->transaction->status
            $status = strtolower($payment->status ?? '');
            if (empty($status) && isset($payment->transaction->status)) {
                $status = strtolower($payment->transaction->status);
            }

            if (in_array($status, ['complete', 'success', 'completed', 'successful'])) {
                $transaction->update(['status' => 'successful']);
                $this->fulfillOrder($transaction);
                return 'successful';
            } elseif (in_array($status, ['failed', 'expired'])) {
                $transaction->update(['status' => 'failed']);
                return 'failed';
            }

            return $status ?: 'pending';
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            Log::error("Error checking transaction $reference: " . $msg);

            if (str_contains(strtolower($msg), 'not found')) {
                return 'reference_not_found';
            }

            return 'error';
        }
    }

    /**
     * Action to perform when order is successful
     */
    public function fulfillOrder(\App\Models\Transaction $transaction): void
    {
        $metadata = $transaction->metadata;

        if (!$metadata || !isset($metadata['action'])) {
            return;
        }

        try {
            switch ($metadata['action']) {
                case 'buy_formation':
                    $formationId = $metadata['formation_id'];
                    $user = $transaction->user;
                    if ($user && !$user->formations()->where('formation_id', $formationId)->exists()) {
                        $user->formations()->attach($formationId, [
                            'status' => 'purchased',
                            'progress' => 0,
                        ]);
                    }
                    break;

                case 'visit_fee':
                    $visitId = $metadata['visit_id'];
                    $visit = \App\Models\Visit::find($visitId);
                    if ($visit) {
                        $visit->update([
                            'fee_payment_status' => 'paid',
                            'fee_payment_method' => 'notchpay',
                        ]);
                    }
                    break;

                case 'rental_payment':
                    $rentalId = $metadata['rental_id'];
                    $rental = \App\Models\Rental::find($rentalId);
                    if ($rental) {
                        $rental->update([
                            'payment_phase_status' => 'paid',
                            'payment_method' => 'notchpay',
                        ]);
                    }
                    break;
            }
            Log::info("Transaction $transaction->reference fulfilled successfully.");
        } catch (\Exception $e) {
            Log::error('Fulfill order error: ' . $e->getMessage());
        }
    }
}
