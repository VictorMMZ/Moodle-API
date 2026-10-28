<?php

namespace App\Http\Controllers;

use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $messages = Message::query()
            ->where('sender_id', $userId)
            ->orWhere('receiver_id', $userId)
            ->latest()
            ->get();

        return response()->json($messages);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'receiver_id' => ['required', 'integer', 'exists:users,id'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ]);

        $message = $request->user()->sentMessages()->create($data);

        return response()->json($message, 201);
    }

    public function show(Request $request, Message $message): JsonResponse
    {
        if (! $this->isParticipant($request, $message)) {
            return response()->json(['message' => 'No tienes acceso a este mensaje.'], 403);
        }

        return response()->json($message);
    }

    public function update(Request $request, Message $message): JsonResponse
    {
        $user = $request->user();

        if ($message->receiver_id !== $user->id) {
            return response()->json(['message' => 'Solo el destinatario puede marcar el mensaje como leído.'], 403);
        }

        $message->update(['read_at' => $message->read_at ?? now()]);

        return response()->json($message);
    }

    public function destroy(Request $request, Message $message): JsonResponse
    {
        if (! $this->isParticipant($request, $message)) {
            return response()->json(['message' => 'No tienes permiso para eliminar este mensaje.'], 403);
        }

        $message->delete();

        return response()->json(['message' => 'Mensaje eliminado.']);
    }

    private function isParticipant(Request $request, Message $message): bool
    {
        return $message->sender_id === $request->user()->id
            || $message->receiver_id === $request->user()->id;
    }

    public function markAsRead(Request $request, Message $message): JsonResponse
    {
        $user = $request->user();

        if ($message->receiver_id !== $user->id) {
            return response()->json(['message' => 'Solo el destinatario puede marcar el mensaje como leído.'], 403);
        }

        $message->update(['read_at' => $message->read_at ?? now()]);

        return response()->json($message);
    }

    
}
