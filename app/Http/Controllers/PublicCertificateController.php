<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Services\CertificateRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class PublicCertificateController extends Controller
{
    /**
     * Máximo de tentativas erradas de CPF por token antes de bloquear
     * aquele link especificamente (independente do IP de origem).
     */
    private const MAX_ATTEMPTS_PER_TOKEN = 15;
    private const LOCKOUT_SECONDS = 3600;

    public function show(string $token): JsonResponse
    {
        $certificate = Certificate::where('token', $token)->first();

        if (!$certificate) {
            return response()->json(['message' => 'Certificado não encontrado ou link inválido.'], 404);
        }

        $certificate->loadMissing(['event', 'participant']);

        return response()->json([
            'event' => [
                'title' => $certificate->event->title,
                'date' => optional($certificate->event->date)->format('d/m/Y'),
                'location' => $certificate->event->location,
            ],
            'participant_name_masked' => $this->maskName($certificate->participant->name),
            'code' => $certificate->code,
        ]);
    }

    public function download(Request $request, string $token, CertificateRenderer $renderer): Response|JsonResponse
    {
        $validated = $request->validate([
            'document' => 'required|string|max:20',
        ]);

        $certificate = Certificate::where('token', $token)->first();

        if (!$certificate) {
            return response()->json(['message' => 'Certificado não encontrado ou link inválido.'], 404);
        }

        $lockKey = "cert-doc:{$token}";

        if (RateLimiter::tooManyAttempts($lockKey, self::MAX_ATTEMPTS_PER_TOKEN)) {
            return response()->json(['message' => 'Muitas tentativas. Tente novamente mais tarde.'], 429);
        }

        $certificate->loadMissing('participant');

        $inputDigits = preg_replace('/\D/', '', $validated['document']);
        $documentDigits = preg_replace('/\D/', '', (string) $certificate->participant->document);
        $expectedSuffix = substr($documentDigits, -5);

        if ($inputDigits === '' || $expectedSuffix === '' || $inputDigits !== $expectedSuffix) {
            RateLimiter::hit($lockKey, self::LOCKOUT_SECONDS);

            return response()->json(['message' => 'Os dígitos informados não conferem.'], 422);
        }

        RateLimiter::clear($lockKey);

        $pdf = $renderer->resolveCachedPdf($certificate);

        $certificate->increment('download_count');

        if (!$certificate->first_downloaded_at) {
            $certificate->update(['first_downloaded_at' => now()]);
        }

        $filename = 'certificado-' . Str::slug($certificate->event->title ?? 'evento') . '.pdf';

        return response($pdf, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    private function maskName(string $name): string
    {
        return collect(preg_split('/\s+/', trim($name)))
            ->filter()
            ->map(fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)) . str_repeat('*', max(mb_strlen($word) - 1, 1)))
            ->implode(' ');
    }
}
