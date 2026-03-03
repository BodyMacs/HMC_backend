<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RentalApplication;
use App\Models\Visit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * RentalApplicationController — Actions du futur locataire sur son dossier de candidature.
 *
 * Le dossier ne peut être créé QU'APRÈS une visite complétée (confirmed_by_user + confirmed_by_agent).
 */
class RentalApplicationController extends Controller
{
    /**
     * Soumettre un dossier de candidature.
     * Prérequis : la visite liée doit être "completed".
     */
    public function submit(Request $request): JsonResponse
    {
        $request->validate([
            'property_id'               => 'required|exists:properties,id',
            'visit_id'                  => 'required|exists:visits,id',
            'situation_professionnelle' => 'nullable|string',
            'revenus_mensuels'          => 'nullable|numeric|min:0',
            'has_garant'                => 'nullable|boolean',
            'notes'                     => 'nullable|string',
            'documents'                 => 'nullable|array',
            'documents.*.type'          => 'required_with:documents|string',
            'documents.*.file'          => 'required_with:documents|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $user = Auth::user();

        // Vérifier que la visite appartient à l'utilisateur et est terminée
        $visit = Visit::where('id', $request->visit_id)
            ->where('user_id', $user->id)
            ->where('property_id', $request->property_id)
            ->where('status', 'completed')
            ->first();

        if (! $visit) {
            return response()->json([
                'success' => false,
                'message' => 'Vous devez d\'abord compléter une visite de ce bien avant de soumettre un dossier.',
            ], 422);
        }

        // Vérifier qu'il n'y a pas déjà un dossier pour cette visite
        $existing = RentalApplication::where('user_id', $user->id)
            ->where('visit_id', $request->visit_id)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Un dossier existe déjà pour cette visite.',
                'data'    => $existing,
            ], 422);
        }

        // --- Sauvegarder les documents soumis ---
        $uploadedDocs = [];
        if ($request->has('documents')) {
            foreach ($request->file('documents', []) as $index => $docFile) {
                // Le fichier peut être sous l'index de document ou directement passé selon la manière dont PHP parse la requête multipart.
                // En multipart, ça peut être request()->file("documents.$index.file");
                $file = $docFile['file'] ?? null;
                $type = $request->input("documents.$index.type");

                if ($file && $type) {
                    $path = $file->store('applications_docs', 'public');
                    $uploadedDocs[] = [
                        'type' => $type,
                        'path' => Storage::url($path),
                    ];
                }
            }
        }

        $application = RentalApplication::create([
            'property_id'               => $request->property_id,
            'user_id'                   => $user->id,
            'visit_id'                  => $request->visit_id,
            'agent_id'                  => $visit->agent_id, // Hérite de l'agent de la visite
            'situation_professionnelle' => $request->situation_professionnelle,
            'revenus_mensuels'          => $request->revenus_mensuels,
            'has_garant'                => $request->boolean('has_garant'),
            'notes'                     => $request->notes,
            'documents'                 => $uploadedDocs,
            'status'                    => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Dossier soumis avec succès. L\'agent HomeCameroon va examiner votre dossier.',
            'data'    => $application->load(['property', 'visit']),
        ], 201);
    }

    /**
     * L'utilisateur signe le contrat de bail pré-rempli.
     */
    public function signContract(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();

        $application = RentalApplication::where('user_id', $user->id)
            ->where('status', 'validated')
            ->findOrFail($id);

        $application->update([
            'signed_by_applicant' => true,
            'signed_at'           => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Contrat signé. L\'agent va maintenant valider votre signature.',
            'data'    => $application->fresh(),
        ]);
    }

    /**
     * Liste des dossiers de l'utilisateur connecté.
     */
    public function myApplications(): JsonResponse
    {
        $user = Auth::user();

        $applications = RentalApplication::with(['property.primaryImage', 'visit', 'rental'])
            ->where('user_id', $user->id)
            ->latest()
            ->get()
            ->map(function ($app) {
                // Ajouter l'étape actuelle du processus
                $app->current_phase = $this->getCurrentPhase($app);
                return $app;
            });

        return response()->json(['success' => true, 'data' => $applications]);
    }

    /**
     * Détail d'un dossier.
     */
    public function show(int $id): JsonResponse
    {
        $user = Auth::user();

        $application = RentalApplication::with(['property.primaryImage', 'property.agent', 'visit', 'rental'])
            ->where('user_id', $user->id)
            ->findOrFail($id);

        $application->current_phase = $this->getCurrentPhase($application);

        return response()->json(['success' => true, 'data' => $application]);
    }

    /**
     * Détermine la phase actuelle du processus pour un dossier donné.
     */
    private function getCurrentPhase(RentalApplication $app): array
    {
        if ($app->status === 'rejected') {
            return ['phase' => 'rejected', 'label' => 'Dossier rejeté', 'step' => 0];
        }

        if (! $app->rental) {
            // En attente de validation du dossier
            return [
                'phase' => 'application',
                'label' => $app->status === 'pending' ? 'Dossier en cours d\'examen' : 'Dossier validé',
                'step'  => 2,
            ];
        }

        $rental = $app->rental;

        if ($rental->status === 'active') {
            return ['phase' => 'active', 'label' => 'Location active ✓', 'step' => 4];
        }

        if ($rental->payment_phase_status === 'pending') {
            return ['phase' => 'payment', 'label' => 'En attente de paiement', 'step' => 3];
        }

        return ['phase' => 'payment_confirming', 'label' => 'Paiement en cours de confirmation', 'step' => 3];
    }
}
