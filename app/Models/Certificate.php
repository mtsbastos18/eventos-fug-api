<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Certificate extends Model
{
    protected $fillable = [
        'event_id',
        'participant_id',
        'token',
        'code',
        'issued_at',
        'pdf_path',
        'template_hash',
        'email_sent_at',
        'email_status',
        'email_error',
        'first_downloaded_at',
        'download_count',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'email_sent_at' => 'datetime',
            'first_downloaded_at' => 'datetime',
        ];
    }

    protected static function booted()
    {
        static::creating(function (Certificate $certificate) {
            $certificate->token ??= (string) Str::uuid();
            $certificate->code ??= static::generateCode($certificate->event_id);
            $certificate->issued_at ??= now();
        });
    }

    protected static function generateCode(int $eventId): string
    {
        do {
            $code = sprintf(
                'FUG-%s-%d-%s',
                now()->format('Y'),
                $eventId,
                strtoupper(Str::random(4)),
            );
        } while (static::where('code', $code)->exists());

        return $code;
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function participant()
    {
        return $this->belongsTo(Participant::class);
    }
}
