<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PropertyComment;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PropertyCommentController extends Controller
{
    /**
     * GET /api/properties/{identifier}/comments
     */
    public function index($identifier): JsonResponse
    {
        $property = Property::where(function ($q) use ($identifier) {
            is_numeric($identifier)
                ? $q->where('id', (int)$identifier)
                : $q->where('slug', $identifier);
        })->firstOrFail();

        $comments = PropertyComment::with('user')
            ->where('property_id', $property->id)
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => $comments,
        ]);
    }

    /**
     * POST /api/properties/{identifier}/comments
     */
    public function store(Request $request, $identifier): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string|max:2000',
        ]);

        $property = Property::where(function ($q) use ($identifier) {
            is_numeric($identifier)
                ? $q->where('id', (int)$identifier)
                : $q->where('slug', $identifier);
        })->firstOrFail();

        $comment = PropertyComment::create([
            'property_id' => $property->id,
            'user_id'     => $request->user()->id,
            'content'     => $validated['content'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Commentaire publié avec succès.',
            'data'    => $comment->load('user'),
        ], 201);
    }

    /**
     * DELETE /api/comments/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $comment = PropertyComment::findOrFail($id);

        if ($comment->user_id !== $user->id && $user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé.',
            ], 403);
        }

        $comment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Commentaire supprimé.',
        ]);
    }

    /**
     * PUT /api/comments/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string|max:2000',
        ]);

        $user = $request->user();
        $comment = PropertyComment::findOrFail($id);

        if ($comment->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé.',
            ], 403);
        }

        // Check 24h limit
        if ($comment->created_at->diffInHours(now()) >= 24) {
            return response()->json([
                'success' => false,
                'message' => 'Le délai de modification (24h) est dépassé.',
            ], 403);
        }

        $comment->update([
            'content' => $validated['content'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Commentaire modifié.',
            'data'    => $comment->load('user'),
        ]);
    }
}
