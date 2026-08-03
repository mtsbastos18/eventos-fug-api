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
    ];

    protected $casts = [
        'date' => 'datetime',
    ];

    protected $appends = ['image_url'];

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
