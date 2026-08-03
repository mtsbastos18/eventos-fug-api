<?php

namespace App\Jobs;

use App\Mail\CertificateAvailable;
use App\Models\Certificate;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendCertificateEmail implements ShouldQueue
{
    use Queueable;

    /**
     * O número de vezes que o job pode ser tentado.
     */
    public $tries = 3;

    /**
     * O número de segundos para aguardar antes de tentar novamente o job.
     */
    public $backoff = 60;

    public $certificate;

    /**
     * Create a new job instance.
     */
    public function __construct(Certificate $certificate)
    {
        $this->certificate = $certificate;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->certificate->loadMissing(['participant', 'event']);

        Mail::to($this->certificate->participant->email)
            ->send(new CertificateAvailable($this->certificate));

        $this->certificate->update([
            'email_status' => 'sent',
            'email_sent_at' => now(),
            'email_error' => null,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        $this->certificate->update([
            'email_status' => 'failed',
            'email_error' => $exception->getMessage(),
        ]);
    }
}
