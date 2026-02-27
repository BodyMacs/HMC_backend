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
            Log::error('NotchPay Initialize Exception: '.$e->getMessage());

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
            Log::error('NotchPay Verify Exception: '.$e->getMessage());

            throw $e;
        }
    }
}
