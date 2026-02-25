<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Formation;
use App\Models\UserFormation;
use App\Models\Transaction;
use Illuminate\Http\Request;

class FormationController extends Controller
{
    public function index()
    {
        $formations = Formation::where('status', 'active')->get();
        return response()->json([
            'success' => true,
            'data' => $formations
        ]);
    }

    public function myFormations(Request $request)
    {
        $user = $request->user();
        $formations = $user->formations()->get();

        return response()->json([
            'success' => true,
            'data' => $formations
        ]);
    }

    public function purchase(Request $request, Formation $formation)
    {
        $user = $request->user();

        // Check if already purchased
        if ($user->formations()->where('formation_id', $formation->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Formation déjà achetée.'
            ], 422);
        }

        // Simulate payment/transaction
        Transaction::create([
            'user_id' => $user->id,
            'amount' => $formation->price,
            'type' => 'debit',
            'description' => "Achat de la formation: {$formation->title}",
            'status' => 'completed'
        ]);

        $user->formations()->attach($formation->id, [
            'status' => 'purchased',
            'progress' => 0
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Paiement effectué et formation débloquée.'
        ]);
    }

    public function show(Request $request, Formation $formation)
    {
        $user = $request->user();
        $userFormation = UserFormation::where('user_id', $user->id)
            ->where('formation_id', $formation->id)
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'formation' => $formation,
                'user_progress' => $userFormation
            ]
        ]);
    }
}
