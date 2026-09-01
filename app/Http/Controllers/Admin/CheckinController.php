<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Participant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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
     * Check-in manual, a partir da listagem de participantes. Além de confirmar a
     * entrada, permite corrigir os dados do participante numa única operação
     * (modal de confirmação de entrada) — se o check-in já tiver sido feito por
     * outra requisição concorrente, as edições são descartadas junto com ele.
     */
    public function byParticipant(Request $request, Event $event, Participant $participant): JsonResponse
    {
        if ((int) $participant->event_id !== (int) $event->id) {
            return response()->json(['message' => 'Participante não pertence a este evento.'], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('participants')->where('event_id', $event->id)->ignore($participant->id),
            ],
            'phone' => 'required|string|max:20',
            'document' => [
                'required',
                'string',
                'max:20',
                Rule::unique('participants')->where('event_id', $event->id)->ignore($participant->id),
            ],
            'company' => 'nullable|string|max:255',
            'position' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'additional_info' => 'nullable|string|max:300',
        ]);

        return DB::transaction(function () use ($participant, $validated) {
            $locked = Participant::whereKey($participant->id)->lockForUpdate()->firstOrFail();

            if ($locked->checked_in_at !== null) {
                return response()->json([
                    'message' => 'Check-in já realizado.',
                    'checked_in_at' => $locked->checked_in_at,
                    'participant' => $locked,
                ], 422);
            }

            $locked->update($validated + ['checked_in_at' => now()]);

            return response()->json([
                'message' => 'Check-in realizado com sucesso.',
                'participant' => $locked,
            ]);
        });
    }

    /**
     * Check-in em lote, sem edição de dados — usado pela emissão de etiquetas
     * (toggle "Fazer check-in ao imprimir"). Participantes que já tinham
     * check-in são apenas reportados em already_checked_in, sem erro.
     */
    public function bulkCheckin(Request $request, Event $event): JsonResponse
    {
        $validated = $request->validate([
            'participant_ids' => 'required|array|min:1',
            'participant_ids.*' => 'integer',
        ]);

        $participants = Participant::where('event_id', $event->id)
            ->whereIn('id', $validated['participant_ids'])
            ->get(['id', 'checked_in_at']);

        $alreadyCheckedIn = $participants->whereNotNull('checked_in_at')->pluck('id')->values();
        $toCheckIn = $participants->whereNull('checked_in_at')->pluck('id')->values();

        if ($toCheckIn->isNotEmpty()) {
            Participant::whereIn('id', $toCheckIn)->update(['checked_in_at' => now()]);
        }

        return response()->json([
            'checked_in' => $toCheckIn,
            'already_checked_in' => $alreadyCheckedIn,
        ]);
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
