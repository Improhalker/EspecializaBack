<?php

namespace App\Models;

use Database\Factories\TestimonialFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Testimonial extends Model
{
    /** @use HasFactory<TestimonialFactory> */
    use HasFactory;

    protected $fillable = ['name', 'content', 'rating', 'avatar_media_id', 'course_id', 'is_published', 'sort_order'];

    protected function casts(): array
    {
        return ['rating' => 'integer', 'is_published' => 'boolean', 'sort_order' => 'integer'];
    }

    public function avatarMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'avatar_media_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return array{url: string, alt_text: string, width: ?int, height: ?int}|null */
    public function resolvedAvatar(): ?array
    {
        $media = $this->avatarMedia;
        $url = $media?->deliveryUrl();
        if (! $url) {
            return null;
        }

        $alt = $media->alt_is_custom && filled($media->alt_text) ? $media->alt_text : $this->name;

        return ['url' => $url, 'alt_text' => $alt, 'width' => $media->width, 'height' => $media->height];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }
}
