<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CertificateTemplate;
use App\Models\Event;
use App\Services\CertificateRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CertificateTemplateController extends Controller
{
    public function show(Event $event): JsonResponse
    {
        $template = $event->certificateTemplate;

        return response()->json([
            'event_id' => $event->id,
            'background_path' => $template->background_path ?? null,
            'background_url' => $this->backgroundUrl($template),
            'page_size' => $template->page_size ?? 'A4',
            'orientation' => $template->orientation ?? 'landscape',
            'fields' => $template->fields ?? [],
            'is_published' => $template->is_published ?? false,
            'updated_at' => $template->updated_at ?? null,
        ]);
    }

    public function store(Request $request, Event $event): JsonResponse
    {
        $validated = $request->validate([
            'background' => 'nullable|image|mimes:jpg,jpeg,png|max:5120',
            'page_size' => 'nullable|in:A4',
            'orientation' => 'nullable|in:landscape,portrait',
            'fields' => 'nullable|string',
            'is_published' => 'nullable|boolean',
        ]);

        $template = CertificateTemplate::firstOrNew(['event_id' => $event->id]);
        $template->event_id = $event->id;

        if ($request->hasFile('background')) {
            if ($template->background_path) {
                Storage::disk(env('FILESYSTEM_DISK'))->delete($template->background_path);
            }

            $extension = $request->file('background')->getClientOriginalExtension();
            $filename = $event->id . '-' . Str::random(8) . '.' . $extension;
            $template->background_path = $request->file('background')
                ->storeAs('certificates/backgrounds', $filename, env('FILESYSTEM_DISK'));
        }

        if ($request->has('fields')) {
            $decoded = json_decode((string) $request->input('fields'), true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                return response()->json(['message' => 'O campo fields precisa ser um JSON válido.'], 422);
            }

            $template->fields = $decoded;
        } elseif (!$template->exists) {
            $template->fields = [];
        }

        $template->page_size = $validated['page_size'] ?? $template->page_size ?? 'A4';
        $template->orientation = $validated['orientation'] ?? $template->orientation ?? 'landscape';

        if (array_key_exists('is_published', $validated)) {
            $template->is_published = $validated['is_published'];
        } elseif (!$template->exists) {
            $template->is_published = false;
        }

        $template->save();

        return response()->json($template->fresh());
    }

    public function preview(Request $request, Event $event, CertificateRenderer $renderer): Response
    {
        $validated = $request->validate([
            'background' => 'nullable|image|mimes:jpg,jpeg,png|max:5120',
            'page_size' => 'nullable|in:A4',
            'orientation' => 'nullable|in:landscape,portrait',
            'fields' => 'nullable|string',
        ]);

        $previewTemplate = clone ($event->certificateTemplate ?? new CertificateTemplate([
            'event_id' => $event->id,
            'page_size' => 'A4',
            'orientation' => 'landscape',
            'fields' => [],
        ]));

        if ($request->has('fields')) {
            $decoded = json_decode((string) $request->input('fields'), true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                return response()->json(['message' => 'O campo fields precisa ser um JSON válido.'], 422);
            }

            $previewTemplate->fields = $decoded;
        }

        if (!empty($validated['page_size'])) {
            $previewTemplate->page_size = $validated['page_size'];
        }

        if (!empty($validated['orientation'])) {
            $previewTemplate->orientation = $validated['orientation'];
        }

        $temporaryBackgroundPath = null;

        if ($request->hasFile('background')) {
            $extension = $request->file('background')->getClientOriginalExtension();
            $temporaryBackgroundPath = $request->file('background')
                ->storeAs('certificates/tmp', Str::uuid() . '.' . $extension, env('FILESYSTEM_DISK'));
            $previewTemplate->background_path = $temporaryBackgroundPath;
        }

        $data = $renderer->dataForSample($event);
        $pdf = $renderer->pdf($previewTemplate, $data);

        if ($temporaryBackgroundPath) {
            Storage::disk(env('FILESYSTEM_DISK'))->delete($temporaryBackgroundPath);
        }

        return response($pdf, 200)->header('Content-Type', 'application/pdf');
    }

    public function copyFrom(Event $event, Event $source): JsonResponse
    {
        $sourceTemplate = $source->certificateTemplate;

        if (!$sourceTemplate) {
            return response()->json(['message' => 'O evento de origem não tem uma matriz de certificado.'], 404);
        }

        $template = CertificateTemplate::firstOrNew(['event_id' => $event->id]);
        $template->event_id = $event->id;

        if ($sourceTemplate->background_path) {
            $disk = Storage::disk(env('FILESYSTEM_DISK'));
            $extension = pathinfo($sourceTemplate->background_path, PATHINFO_EXTENSION);
            $newPath = 'certificates/backgrounds/' . $event->id . '-' . Str::random(8) . '.' . $extension;
            $disk->copy($sourceTemplate->background_path, $newPath);

            if ($template->background_path) {
                $disk->delete($template->background_path);
            }

            $template->background_path = $newPath;
        }

        $template->page_size = $sourceTemplate->page_size;
        $template->orientation = $sourceTemplate->orientation;
        $template->fields = $sourceTemplate->fields;
        $template->is_published = false;
        $template->save();

        return response()->json($template->fresh());
    }

    public function destroy(Event $event): JsonResponse
    {
        $template = $event->certificateTemplate;

        if (!$template) {
            return response()->json(['message' => 'Este evento não possui uma matriz de certificado.'], 404);
        }

        if ($template->background_path) {
            Storage::disk(env('FILESYSTEM_DISK'))->delete($template->background_path);
        }

        $template->delete();

        return response()->json(['message' => 'Matriz de certificado excluída com sucesso.']);
    }

    private function backgroundUrl(?CertificateTemplate $template): ?string
    {
        if (!$template || !$template->background_path) {
            return null;
        }

        return Storage::disk(env('FILESYSTEM_DISK'))->url($template->background_path);
    }
}
