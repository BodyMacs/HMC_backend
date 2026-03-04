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
     * Gère le retour de NotchPay après une tentative de paiement (GET redirect)
     */
    public function callback(Request $request)
    {
        $reference = $request->query('reference') ?? $request->query('trx_reference');
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');

        if (! $reference) {
            Log::warning('Callback NotchPay: Référence manquante', ['query' => $request->all()]);
            return redirect($frontendUrl . '/payment?status=error&message=reference_missing');
        }

        $status = $this->notchPayService->checkAndFulfillTransaction($reference);

        if ($status === 'successful') {
            return redirect($frontendUrl . '/payment?status=success&reference=' . $reference);
        } elseif ($status === 'failed') {
            return redirect($frontendUrl . '/payment?status=failed&reference=' . $reference);
        } elseif ($status === 'not_found' || $status === 'error') {
            return redirect($frontendUrl . '/payment?status=error&message=' . $status);
        }

        // Sinon on est sans doute encore en "pending" ou "processing"
        return redirect($frontendUrl . '/payment?status=' . $status . '&reference=' . $reference);
    }

    /**
     * Gère les notifications asynchrones de NotchPay (POST Webhook)
     */
    public function webhook(Request $request)
    {
        $payload = $request->all();
        Log::info('Webhook NotchPay reçu:', $payload);

        $reference = $payload['data']['reference'] ?? $payload['reference'] ?? null;

        if (! $reference) {
            return response()->json(['message' => 'No reference found'], 400);
        }

        $result = $this->notchPayService->checkAndFulfillTransaction($reference);

        return response()->json(['message' => 'Processed', 'status' => $result]);
    }

    /**
     * Récupère le statut d'une transaction locale
     */
    public function getTransactionStatus($reference)
    {
        $transaction = Transaction::where('reference', $reference)->first();

        if (! $transaction) {
            return response()->json(['success' => false, 'message' => 'Transaction introuvable'], 404);
        }

        // Si pas réussi localement, on re-tente un check complet
        if ($transaction->status !== 'successful') {
            $this->notchPayService->checkAndFulfillTransaction($reference);
            $transaction->refresh();
        }

        return response()->json([
            'success' => true,
            'status' => $transaction->status,
            'reference' => $reference,
        ]);
    }
}
