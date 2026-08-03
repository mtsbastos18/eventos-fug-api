<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CertificateTemplate extends Model
{
    protected $fillable = [
        'event_id',
        'background_path',
        'page_size',
        'orientation',
        'fields',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'fields' => 'array',
            'is_published' => 'boolean',
        ];
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
