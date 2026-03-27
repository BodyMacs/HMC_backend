<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Formation;
use App\Models\Transaction;
use App\Models\UserFormation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FormationController extends Controller
{
    public function index(): JsonResponse
    {
        $formations = Formation::where('status', 'active')->get();

        return response()->json([
            'success' => true,
            'data' => $formations,
        ]);
    }

    public function myFormations(Request $request): JsonResponse
    {
        $user = $request->user();
        $formations = $user->formations()->get();

        return response()->json([
            'success' => true,
            'data' => $formations,
        ]);
    }

    public function purchase(Request $request, Formation $formation): JsonResponse
    {
        $user = $request->user();

        // Check if already purchased
        if ($user->formations()->where('formation_id', $formation->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Formation déjà achetée.',
            ], 422);
        }

        // Initialize NotchPay payment
        $notchPayService = app(\App\Services\NotchPayService::class);
        $reference = 'FM_'.$user->id.'_'.time();

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'reference' => $reference,
            'type' => 'payment',
            'amount' => $formation->price,
            'currency' => 'XAF',
            'status' => 'pending',
            'payment_method' => 'momo', // Default fallback
            'metadata' => [
                'action' => 'buy_formation',
                'formation_id' => $formation->id,
                'description' => "Achat de la formation: {$formation->title}",
            ],
        ]);

        try {
            $payment = $notchPayService->initializePayment([
                'amount' => $formation->price,
                'email' => $user->email,
                'reference' => $reference,
                'description' => "Achat de la formation: {$formation->title}",
                'metadata' => [
                    'action' => 'buy_formation',
                    'formation_id' => $formation->id,
                ],
            ]);

            return response()->json([
                'success' => true,
                'redirect_url' => $payment->authorization_url,
                'reference' => $reference,
                'message' => 'Paiement initialisé avec succès.',
            ]);
        } catch (\Exception $e) {
            // Delete pending transaction if init fails
            $transaction->delete();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'initialisation du paiement',
            ], 500);
        }
    }

    public function show(Request $request, Formation $formation): JsonResponse
    {
        $user = $request->user();
        $userFormation = UserFormation::where('user_id', $user->id)
            ->where('formation_id', $formation->id)
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'formation' => $formation,
                'user_progress' => $userFormation,
            ],
        ]);
    }
}
