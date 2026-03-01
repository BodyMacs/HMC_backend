<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    /**
     * Get user roles.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'active_role' => $user->role,
                'available_roles' => $user->roles ?? [$user->role],
            ],
        ]);
    }

    /**
     * Switch current active role.
     */
    public function switch(Request $request)
    {
        $request->validate([
            'role' => 'required|string',
        ]);

        $user = $request->user();
        $role = $request->role;

        if ($user->switchRole($role)) {
            return response()->json([
                'success' => true,
                'message' => "Vous utilisez maintenant l'application en tant que " . $role,
                'data' => [
                    'active_role' => $user->role,
                    'available_roles' => $user->roles,
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => "Vous n'avez pas accès à ce rôle ou il n'existe pas.",
        ], 403);
    }

    /**
     * Acquire a new role.
     */
    public function acquire(Request $request)
    {
        $request->validate([
            'role' => 'required|string|in:bailleur,locataire,prestataire,agent',
        ]);

        $user = $request->user();
        $role = $request->role;

        if (!$user->hasRole($role)) {
            $user->addRole($role);
            return response()->json([
                'success' => true,
                'message' => "Félicitations ! Vous êtes maintenant aussi un " . $role,
                'data' => [
                    'active_role' => $user->role,
                    'available_roles' => $user->roles,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => "Vous possédez déjà ce rôle.",
            'data' => [
                'active_role' => $user->role,
                'available_roles' => $user->roles,
            ],
        ]);
    }
}
