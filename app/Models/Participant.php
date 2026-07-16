<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
    ];

    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
        ];
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
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
