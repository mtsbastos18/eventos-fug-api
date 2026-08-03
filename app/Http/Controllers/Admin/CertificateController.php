<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendCertificateEmail;
use App\Models\Certificate;
use App\Models\Event;
use App\Models\Participant;
use App\Services\CertificateRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class CertificateController extends Controller
{
    public function index(Request $request, Event $event): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $eligible = Participant::where('event_id', $event->id)->whereNotNull('checked_in_at');

        $summary = [
            'checked_in' => (clone $eligible)->count(),
            'issued' => Certificate::where('event_id', $event->id)->count(),
            'sent' => Certificate::where('event_id', $event->id)->where('email_status', 'sent')->count(),
            'failed' => Certificate::where('event_id', $event->id)->where('email_status', 'failed')->count(),
            'downloaded' => Certificate::where('event_id', $event->id)->where('download_count', '>', 0)->count(),
        ];

        $items = $eligible->with('certificate')
            ->orderBy('name')
            ->paginate($validated['per_page'] ?? 20);

        $template = $event->certificateTemplate;

        return response()->json([
            'summary' => $summary,
            'template' => [
                'is_published' => $template->is_published ?? false,
                'updated_at' => $template->updated_at ?? null,
            ],
            'items' => $items,
        ]);
    }

    public function issue(Event $event): JsonResponse
    {
        $eligibleIds = Participant::where('event_id', $event->id)
            ->whereNotNull('checked_in_at')
            ->pluck('id');

        $existingIds = Certificate::where('event_id', $event->id)
            ->whereIn('participant_id', $eligibleIds)
            ->pluck('participant_id');

        $toCreate = $eligibleIds->diff($existingIds)->values();

        DB::transaction(function () use ($event, $toCreate) {
            foreach ($toCreate as $participantId) {
                Certificate::firstOrCreate([
                    'event_id' => $event->id,
                    'participant_id' => $participantId,
                ]);
            }
        });

        return response()->json([
            'issued' => $toCreate->count(),
            'total_certificates' => Certificate::where('event_id', $event->id)->count(),
        ]);
    }

    public function send(Request $request, Event $event): JsonResponse
    {
        $validated = $request->validate([
            'participant_ids' => 'nullable|array',
            'participant_ids.*' => 'integer',
        ]);

        $template = $event->certificateTemplate;

        if (!$template || !$template->is_published) {
            return response()->json(['message' => 'Publique a matriz do certificado antes de enviar.'], 422);
        }

        return DB::transaction(function () use ($event, $validated) {
            $query = Certificate::where('event_id', $event->id)
                ->whereIn('email_status', ['pending', 'failed'])
                ->lockForUpdate();

            if (!empty($validated['participant_ids'])) {
                $query->whereIn('participant_id', $validated['participant_ids']);
            }

            $certificates = $query->get();

            if ($certificates->isNotEmpty()) {
                Certificate::whereIn('id', $certificates->pluck('id'))->update([
                    'email_status' => 'queued',
                    'email_error' => null,
                ]);
            }

            foreach ($certificates as $certificate) {
                SendCertificateEmail::dispatch($certificate);
            }

            return response()->json(['queued' => $certificates->count()]);
        });
    }

    public function download(Event $event, Certificate $certificate, CertificateRenderer $renderer): Response
    {
        if ((int) $certificate->event_id !== (int) $event->id) {
            abort(404);
        }

        $pdf = $renderer->resolveCachedPdf($certificate);

        return response($pdf, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="certificado-' . $certificate->code . '.pdf"');
    }
}
