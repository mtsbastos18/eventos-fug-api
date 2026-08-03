<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Event;
use App\Models\Participant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class CertificateRenderer
{
    /**
     * Dimensões (mm, orientação retrato) suportadas para a página do certificado.
     */
    private const PAGE_SIZES = [
        'A4' => ['width' => 210.0, 'height' => 297.0],
    ];

    public function html(CertificateTemplate $template, array $data): string
    {
        [$widthMm, $heightMm] = $this->pageDimensions($template);
        $backgroundDataUri = $this->backgroundDataUri($template);

        $fieldsHtml = collect($template->fields ?? [])
            ->map(fn (array $field) => $this->renderField($field, $data, $heightMm))
            ->implode('');

        $backgroundStyle = $backgroundDataUri
            ? "background-image: url('{$backgroundDataUri}'); background-size: 100% 100%; background-repeat: no-repeat;"
            : '';

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 0; }
    body { margin: 0; padding: 0; }
    .page {
        position: relative;
        width: {$widthMm}mm;
        height: {$heightMm}mm;
        font-family: helvetica, sans-serif;
        {$backgroundStyle}
    }
    .field {
        position: absolute;
        margin: 0;
        line-height: 1.2;
    }
</style>
</head>
<body>
    <div class="page">{$fieldsHtml}</div>
</body>
</html>
HTML;
    }

    public function pdf(CertificateTemplate $template, array $data): string
    {
        $html = $this->html($template, $data);
        [$widthMm, $heightMm] = $this->pageDimensions($template);

        $pdf = Pdf::loadHTML($html)
            ->setPaper([0, 0, $this->mmToPt($widthMm), $this->mmToPt($heightMm)]);

        return $pdf->output();
    }

    /**
     * Renderiza (ou lê do cache em disco) o PDF de um certificado já emitido.
     * O cache é invalidado automaticamente quando a matriz do evento muda.
     */
    public function resolveCachedPdf(Certificate $certificate): string
    {
        $certificate->loadMissing(['event.certificateTemplate', 'participant']);
        $template = $certificate->event->certificateTemplate;
        $disk = $this->pdfDisk();

        if (!$template) {
            // A matriz pode ter sido excluída depois que este certificado já foi
            // renderizado; um PDF já em cache continua servível normalmente.
            if ($certificate->pdf_path && $disk->exists($certificate->pdf_path)) {
                return $disk->get($certificate->pdf_path);
            }

            throw new \RuntimeException('O evento não possui uma matriz de certificado configurada.');
        }

        $currentHash = $this->templateHash($template);

        if (
            $certificate->pdf_path
            && $certificate->template_hash === $currentHash
            && $disk->exists($certificate->pdf_path)
        ) {
            return $disk->get($certificate->pdf_path);
        }

        $pdf = $this->pdf($template, $this->dataForCertificate($certificate));
        $path = "certificates/{$certificate->event_id}/{$certificate->token}.pdf";
        $disk->put($path, $pdf);

        $certificate->forceFill([
            'pdf_path' => $path,
            'template_hash' => $currentHash,
        ])->save();

        return $pdf;
    }

    public function templateHash(CertificateTemplate $template): string
    {
        return hash('sha256', json_encode($template->fields) . '|' . $template->background_path);
    }

    /**
     * Disco onde os PDFs já renderizados ficam em cache. Deliberadamente NÃO é
     * env('FILESYSTEM_DISK')/config('filesystems.default') — nesta instalação esse
     * valor aponta para o disco 'public' (servido por trás de /storage), o que
     * exporia o PDF (nome completo do participante) via URL direta a quem
     * conhecesse o token, pulando por completo a confirmação de CPF. O disco
     * 'local' (storage/app/private) não é servido publicamente.
     */
    private function pdfDisk()
    {
        return Storage::disk('local');
    }

    public function dataForCertificate(Certificate $certificate): array
    {
        $certificate->loadMissing(['event', 'participant']);

        return $this->buildData($certificate->event, $certificate->participant, $certificate);
    }

    public function dataForSample(Event $event): array
    {
        $participant = $event->participants()->whereNotNull('checked_in_at')->first()
            ?? new Participant([
                'name' => 'Nome de Exemplo da Silva',
                'document' => '12345678900',
                'company' => 'Empresa Exemplo Ltda',
                'position' => 'Cargo de Exemplo',
            ]);

        return $this->buildData($event, $participant, null);
    }

    private function buildData(Event $event, Participant $participant, ?Certificate $certificate): array
    {
        return [
            'participante' => [
                'nome' => (string) $participant->name,
                'documento' => $this->formatDocument($participant->document),
                'empresa' => (string) ($participant->company ?? ''),
                'cargo' => (string) ($participant->position ?? ''),
            ],
            'evento' => [
                'titulo' => (string) $event->title,
                'subtitulo' => (string) ($event->subtitle ?? ''),
                'data' => $event->date ? $event->date->translatedFormat('d \d\e F \d\e Y') : '',
                'local' => (string) ($event->location ?? ''),
                'carga_horaria' => $event->workload_hours ? (string) $event->workload_hours : '',
            ],
            'certificado' => [
                'codigo' => $certificate->code ?? 'FUG-EXEMPLO-0000',
                'data_emissao' => ($certificate?->issued_at ?? now())->translatedFormat('d/m/Y'),
            ],
        ];
    }

    private function formatDocument(?string $document): string
    {
        $digits = preg_replace('/\D/', '', (string) $document);

        if (strlen($digits) !== 11) {
            return (string) $document;
        }

        return substr($digits, 0, 3) . '.' . substr($digits, 3, 3) . '.' . substr($digits, 6, 3) . '-' . substr($digits, 9, 2);
    }

    private function renderField(array $field, array $data, float $pageHeightMm): string
    {
        $x = (float) ($field['x'] ?? 50);
        $y = (float) ($field['y'] ?? 50);
        $width = (float) ($field['width'] ?? 50);
        $requestedAlign = $field['align'] ?? 'left';
        $align = in_array($requestedAlign, ['left', 'center', 'right'], true) ? $requestedAlign : 'left';
        $fontSize = (float) ($field['font_size'] ?? 14);
        $fontFamily = (string) ($field['font_family'] ?? 'helvetica');
        $fontWeight = (string) ($field['font_weight'] ?? 'normal');
        $color = (string) ($field['color'] ?? '#000000');
        $uppercase = (bool) ($field['uppercase'] ?? false);

        $text = isset($field['text'])
            ? $this->interpolate((string) $field['text'], $data)
            : $this->resolveVariable($field['variable'] ?? null, $data);

        if ($uppercase) {
            $text = mb_strtoupper($text);
        }

        // x/y é o ponto central do campo; convertemos para a borda esquerda/superior
        // que o CSS absoluto espera, usando a largura e uma altura de linha estimadas.
        $left = match ($align) {
            'center' => $x - ($width / 2),
            'right' => $x - $width,
            default => $x,
        };

        $lineHeightPercentOfPage = (($fontSize * 1.2) / $this->mmToPt($pageHeightMm)) * 100;
        $top = $y - ($lineHeightPercentOfPage / 2);

        $style = sprintf(
            'left: %s%%; top: %s%%; width: %s%%; text-align: %s; font-size: %spt; font-family: %s; font-weight: %s; color: %s;',
            $this->fmt($left),
            $this->fmt($top),
            $this->fmt($width),
            htmlspecialchars($align, ENT_QUOTES, 'UTF-8'),
            $this->fmt($fontSize),
            htmlspecialchars($fontFamily, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($fontWeight, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($color, ENT_QUOTES, 'UTF-8'),
        );

        $safeText = nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));

        return "<div class=\"field\" style=\"{$style}\">{$safeText}</div>";
    }

    private function interpolate(string $text, array $data): string
    {
        return preg_replace_callback('/\{\{\s*([\w.]+)\s*\}\}/', function (array $matches) use ($data) {
            return $this->resolveVariable($matches[1], $data);
        }, $text) ?? '';
    }

    private function resolveVariable(?string $path, array $data): string
    {
        if (!$path) {
            return '';
        }

        $value = $data;

        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return '';
            }
            $value = $value[$segment];
        }

        return $value === null ? '' : (string) $value;
    }

    private function pageDimensions(CertificateTemplate $template): array
    {
        $size = self::PAGE_SIZES[$template->page_size] ?? self::PAGE_SIZES['A4'];

        return $template->orientation === 'landscape'
            ? [$size['height'], $size['width']]
            : [$size['width'], $size['height']];
    }

    private function mmToPt(float $mm): float
    {
        return $mm * 72 / 25.4;
    }

    private function fmt(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }

    private function backgroundDataUri(CertificateTemplate $template): string
    {
        if (!$template->background_path) {
            return '';
        }

        $disk = Storage::disk(env('FILESYSTEM_DISK'));

        if (!$disk->exists($template->background_path)) {
            return '';
        }

        $contents = $disk->get($template->background_path);
        $mime = $disk->mimeType($template->background_path) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode($contents);
    }
}
