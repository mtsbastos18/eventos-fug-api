<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'subtitle',
        'slug',
        'date',
        'location',
        'description',
        'capacity',
        'workload_hours',
        'image_path',
        'archived_at',
    ];

    protected $casts = [
        'date' => 'datetime',
        'archived_at' => 'datetime',
    ];

    protected $appends = ['image_url', 'is_archived'];

    protected static function booted()
    {
        static::saving(function ($event) {
            if (empty($event->slug)) {
                $event->slug = Str::slug($event->title);

                // Ensure slug is unique
                $originalSlug = $event->slug;
                $count = 1;
                while (Event::where('slug', $event->slug)->where('id', '!=', $event->id ?? 0)->exists()) {
                    $event->slug = "{$originalSlug}-{$count}";
                    $count++;
                }
            }
        });
    }

    public function getImageUrlAttribute()
    {
        return $this->image_path ? url('storage/' . $this->image_path) : null;
    }

    public function getIsArchivedAttribute(): bool
    {
        return $this->archived_at !== null;
    }

    public function scopeNotArchived($query)
    {
        return $query->whereNull('archived_at');
    }

    public function scopeArchived($query)
    {
        return $query->whereNotNull('archived_at');
    }

    public function participants()
    {
        return $this->hasMany(Participant::class);
    }
    public function postDetail()
    {
        return $this->hasOne(EventPostDetail::class);
    }

    public function certificateTemplate()
    {
        return $this->hasOne(CertificateTemplate::class);
    }

    public function certificates()
    {
        return $this->hasMany(Certificate::class);
    }
}
