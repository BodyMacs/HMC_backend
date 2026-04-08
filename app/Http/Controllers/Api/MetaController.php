<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\Intervention;
use App\Models\Notification;
use App\Models\Property;
use App\Models\PropertyRequest;
use App\Models\Rental;
use App\Models\RentalApplication;
use App\Models\Visit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetaController extends Controller
{
    /**
     * Retourne les compteurs pour les badges de la sidebar en fonction du rôle actuel.
     */
    public function sidebarStats(Request $request): JsonResponse
    {
        $user = $request->user();
        $role = $request->header('X-Current-Role', $user->role);
        $stats = [];

        switch ($role) {
            case 'admin':
                $stats = [
                    'AdminDemandesPublication' => PropertyRequest::where('status', 'pending')->count(),
                    'AdminBiensAnnonces' => Property::where('status', 'pending')->count(),
                    'AdminServices' => Intervention::where('status', 'pending')->count(),
                    'PublicationRequests' => PropertyRequest::where('status', 'pending')->count(),
                ];
                break;

            case 'agent':
                $stats = [
                    'AgentMissions' => Visit::where('agent_id', $user->id)->where('status', 'pending')->count()
                        + RentalApplication::where('agent_id', $user->id)->where('status', 'pending')->count()
                        + PropertyRequest::where('agent_id', $user->id)->where('status', 'assigned')->count(),
                    'AgentBiens' => Property::where('agent_id', $user->id)->count(),
                    'AgentAgenda' => Visit::where('agent_id', $user->id)->whereDate('scheduled_at', now()->toDateString())->count(),
                ];
                break;

            case 'bailleur':
                $stats = [
                    'BailleurMesBiens' => Property::where('user_id', $user->id)->count(),
                    'BailleurMesLocataires' => Rental::whereHas('property', fn($q) => $q->where('user_id', $user->id))
                        ->where('status', 'active')->count(),
                    'PublicationRequests' => PropertyRequest::where('user_id', $user->id)->count(),
                ];
                break;

            case 'locataire':
                $stats = [
                    'LocataireMesLocations' => Rental::where('tenant_id', $user->id)->where('status', 'active')->count(),
                    'LocataireMesPaiements' => Rental::where('tenant_id', $user->id)->where('payment_status', 'unpaid')->count(),
                    'LocataireInterventions' => Intervention::where('requester_id', $user->id)->where('status', '!=', 'completed')->count(),
                    'MesFavoris' => Favorite::where('user_id', $user->id)->count(),
                ];
                break;

            case 'client':
                $stats = [
                    'PublicationRequests' => PropertyRequest::where('user_id', $user->id)->count(),
                    'MonSuivi' => RentalApplication::where('user_id', $user->id)->whereIn('status', ['pending', 'validated'])->count()
                        + Visit::where('user_id', $user->id)->where('status', 'pending')->count(),
                    'MesFavoris' => Favorite::where('user_id', $user->id)->count(),
                ];
                break;

            case 'prestataire':
                $stats = [
                    'PrestataireInterventions' => Intervention::whereHas('service', fn($q) => $q->where('provider_id', $user->id))
                        ->whereIn('status', ['pending', 'in_progress'])->count(),
                ];
                break;
        }

        return response()->json([
            'success' => true,
            'data'    => array_merge($stats, [
                'unread_notifications_count' => Notification::where('user_id', $user->id)->where('is_read', false)->count(),
            ]),
        ]);
    }
    public function settings(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'visit_fee' => Visit::getVisitFee(),
            ],
        ]);
    }
}
