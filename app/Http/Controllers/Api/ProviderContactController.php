<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProviderContactController extends Controller
{
    /**
     * Contact a provider directly from their directory profile
     */
    public function contact(Request $request, int $providerId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $provider = User::where('id', $providerId)->where('role', 'prestataire')->first();
        if (!$provider) {
            return response()->json(['success' => false, 'message' => 'Prestataire introuvable.'], 404);
        }

        $user = $request->user();

        // Check if a direct conversation already exists between the two
        $conversation = Conversation::whereNull('service_post_id')
            ->where(function ($query) use ($user, $providerId) {
                $query->where(function ($q) use ($user, $providerId) {
                    $q->where('user_one_id', $user->id)->where('user_two_id', $providerId);
                })->orWhere(function ($q) use ($user, $providerId) {
                    $q->where('user_one_id', $providerId)->where('user_two_id', $user->id);
                });
            })->first();

        if (!$conversation) {
            $conversation = Conversation::create([
                'user_one_id' => $user->id,
                'user_two_id' => $providerId,
                'status' => 'active',
            ]);
        }

        $fullMessage = "**Sujet: " . $request->subject . "**\n\n" . $request->message;

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'content' => $fullMessage,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Votre message a été envoyé au prestataire.',
            'data' => [
                'conversation_id' => $conversation->id
            ]
        ], 201);
    }
}
