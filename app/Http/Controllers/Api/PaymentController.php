<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\NotchPayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    protected $notchPayService;

    public function __construct(NotchPayService $notchPayService)
    {
        $this->notchPayService = $notchPayService;
    }

    /**
     * Gère le retour de NotchPay après une tentative de paiement
     */
    public function callback(Request $request)
    {
        $reference = $request->query('reference');
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');

        if (! $reference) {
            return redirect($frontendUrl.'/payment?status=error&message=reference_missing');
        }

        try {
            $payment = $this->notchPayService->verifyPayment($reference);

            $transaction = Transaction::where('reference', $reference)->first();

            if (! $transaction) {
                return redirect($frontendUrl.'/payment?status=error&message=transaction_not_found');
            }

            $user = $transaction->user;
            $status = strtolower($payment->transaction->status ?? '');

            if (in_array($status, ['complete', 'success', 'completed'])) {
                if ($transaction->status !== 'successful') {
                    // Update Transaction
                    $transaction->update([
                        'status' => 'successful',
                    ]);

                    // Perform action based on transaction metadata
                    $this->fulfillOrder($transaction);
                }

                return redirect($frontendUrl.'/payment?status=success&reference='.$reference);
            } else {
                $transaction->update([
                    'status' => 'failed',
                ]);

                return redirect($frontendUrl.'/payment?status=failed&reference='.$reference);
            }

        } catch (\Exception $e) {
            Log::error('Erreur callback NotchPay:', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            return redirect($frontendUrl.'/payment?status=error&message=processing_error');
        }
    }

    /**
     * Action to perform when order is successful
     */
    private function fulfillOrder(Transaction $transaction): void
    {
        $metadata = $transaction->metadata;

        if (! $metadata || ! isset($metadata['action'])) {
            return;
        }

        try {
            switch ($metadata['action']) {
                case 'buy_formation':
                    $formationId = $metadata['formation_id'];
                    $user = $transaction->user;

                    // Avoid duplicate attachment
                    if (! $user->formations()->where('formation_id', $formationId)->exists()) {
                        $user->formations()->attach($formationId, [
                            'status' => 'purchased',
                            'progress' => 0,
                        ]);
                    }

                    break;

                    // Add other switch cases for rentals, marketplace, etc.
            }
        } catch (\Exception $e) {
            Log::error('Fulfill order error: '.$e->getMessage());
        }
    }
}
