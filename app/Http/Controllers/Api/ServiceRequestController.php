<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ServicePost;
use App\Models\ServicePostResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ServiceRequestController extends Controller
{
    /**
     * List all open service requests (The Job Board)
     */
    public function index(Request $request): JsonResponse
    {
        $query = ServicePost::with(['client', 'category'])
            ->where('status', 'open');

        // Simple filtering by city or category
        if ($request->has('city')) {
            $query->where('city', 'like', "%{$request->city}%");
        }
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        $posts = $query->latest()->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $posts,
        ]);
    }

    /**
     * Create a new service request (User post)
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'required|exists:service_categories,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'city' => 'required|string',
            'neighborhood' => 'nullable|string',
            'urgency' => 'nullable|in:low,medium,high',
            'min_budget' => 'nullable|numeric|min:0',
            'max_budget' => 'nullable|numeric|min:0',
            'preferred_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $post = ServicePost::create([
            'client_id' => $request->user()->id,
            'category_id' => $request->category_id,
            'title' => $request->title,
            'description' => $request->description,
            'city' => $request->city,
            'neighborhood' => $request->neighborhood,
            'urgency' => $request->urgency ?? 'medium',
            'min_budget' => $request->min_budget,
            'max_budget' => $request->max_budget,
            'preferred_date' => $request->preferred_date,
            'status' => 'open',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Demande de service publiée avec succès',
            'data' => $post
        ], 201);
    }

    /**
     * Get details of a service request
     */
    public function show(int $id): JsonResponse
    {
        $post = ServicePost::with(['client', 'category', 'responses.provider'])
            ->find($id);

        if (!$post) {
            return response()->json([
                'success' => false,
                'message' => 'Demande non trouvée'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $post
        ]);
    }

    /**
     * Provider bids on a service request
     */
    public function respond(Request $request, int $postId): JsonResponse
    {
        $post = ServicePost::find($postId);
        if (!$post || $post->status !== 'open') {
            return response()->json([
                'success' => false,
                'message' => 'Cette demande n\'est plus ouverte aux propositions'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'message' => 'required|string',
            'proposed_price' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $response = ServicePostResponse::create([
            'post_id' => $postId,
            'provider_id' => $request->user()->id,
            'message' => $request->message,
            'proposed_price' => $request->proposed_price,
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Votre proposition a été envoyée au client',
            'data' => $response
        ], 201);
    }
    /**
     * Client accepts a provider's response from the Job Board
     */
    public function acceptResponse(Request $request, int $postId, int $responseId): JsonResponse
    {
        $post = ServicePost::findOrFail($postId);

        if ($post->client_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Non autorisé'], 403);
        }

        if ($post->status !== 'open') {
            return response()->json(['success' => false, 'message' => 'Cette demande n\'est plus ouverte'], 400);
        }

        $acceptedResponse = ServicePostResponse::where('post_id', $postId)
            ->where('id', $responseId)
            ->firstOrFail();

        // Mettre à jour les statuts
        $post->status = 'assigned';
        $post->save();

        // Rejeter les autres, accepter celle-ci
        ServicePostResponse::where('post_id', $postId)->update(['status' => 'rejected']);
        $acceptedResponse->status = 'accepted';
        $acceptedResponse->save();

        // ── Création de la conversation ──
        $conversation = \App\Models\Conversation::create([
            'user_one_id' => $post->client_id, // Client
            'user_two_id' => $acceptedResponse->provider_id, // Prestataire
            'service_post_id' => $post->id,
            'status' => 'active',
        ]);

        // Message système automatique
        \App\Models\Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $post->client_id,
            'content' => "J'ai accepté votre offre pour ma demande : " . $post->title . " au prix de " . $acceptedResponse->proposed_price . " FCFA.",
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Offre acceptée ! Une conversation a été ouverte.',
            'data' => [
                'conversation_id' => $conversation->id
            ]
        ]);
    }
}
