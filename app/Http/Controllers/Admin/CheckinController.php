<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Participant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CheckinController extends Controller
{
    /**
     * Check-in via token lido pela câmera (QR Code).
     */
    public function byToken(Request $request, Event $event): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
        ]);

        $token = preg_replace('/^urn:uuid:/i', '', trim($validated['token']));

        $participant = Participant::where('event_id', $event->id)
            ->where('checkin_token', $token)
            ->first();

        if (!$participant) {
            return response()->json(['message' => 'Código não encontrado para este evento.'], 404);
        }

        return $this->confirmCheckin($participant);
    }

    /**
     * Check-in manual, a partir da listagem de participantes.
     */
    public function byParticipant(Event $event, Participant $participant): JsonResponse
    {
        if ((int) $participant->event_id !== (int) $event->id) {
            return response()->json(['message' => 'Participante não pertence a este evento.'], 404);
        }

        return $this->confirmCheckin($participant);
    }

    private function confirmCheckin(Participant $participant): JsonResponse
    {
        $affected = Participant::where('id', $participant->id)
            ->whereNull('checked_in_at')
            ->update(['checked_in_at' => now()]);

        $participant->refresh();

        if ($affected === 0) {
            return response()->json([
                'message' => 'Check-in já realizado.',
                'checked_in_at' => $participant->checked_in_at,
                'participant' => $participant,
            ], 422);
        }

        return response()->json([
            'message' => 'Check-in realizado com sucesso.',
            'participant' => $participant,
        ]);
    }
}
