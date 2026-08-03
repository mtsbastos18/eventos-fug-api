<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Participant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'document',
        'event_id',
        'verification_code',
        'is_verified',
        'company',
        'position',
        'city',
        'checkin_token',
        'checked_in_at',
    ];

    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
            'checked_in_at' => 'datetime',
        ];
    }

    protected static function booted()
    {
        static::creating(function (Participant $participant) {
            $participant->checkin_token ??= (string) Str::uuid();
        });
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function certificate()
    {
        return $this->hasOne(Certificate::class);
    }

    public function scopeSearch($query, ?string $search, ?string $filterType)
    {
        if (!$search) {
            return $query;
        }

        $document = preg_replace('/\D/', '', $search);

        return match ($filterType) {
            'name' => $query->where('name', 'like', "%{$search}%"),
            'email' => $query->where('email', 'like', "%{$search}%"),
            'cpf' => $document !== ''
                ? $query->where('document', 'like', "%{$document}%")
                : $query->whereRaw('0 = 1'),
            default => $query->where(function ($q) use ($search, $document) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");

                if ($document !== '') {
                    $q->orWhere('document', 'like', "%{$document}%");
                }
            }),
        };
    }
}
