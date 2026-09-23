<?php

namespace App\Models;

use App\Services\MediaAltText;
use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    /** @use HasFactory<CourseFactory> */
    use HasFactory;

    protected $fillable = ['category_id', 'name', 'short_name', 'slug', 'summary', 'description', 'requirements', 'cover_image_path', 'cover_media_id', 'cover_alt_text', 'hero_enabled', 'hero_media_id', 'hero_mobile_media_id', 'hero_image_position', 'hero_overlay_preset', 'hero_overlay_opacity', 'meta_title', 'meta_description', 'is_featured', 'is_published', 'sort_order'];

    protected function casts(): array
    {
        return ['requirements' => 'array', 'is_featured' => 'boolean', 'is_published' => 'boolean', 'hero_enabled' => 'boolean', 'hero_overlay_opacity' => 'integer'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function coverMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_media_id');
    }

    public function heroMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'hero_media_id');
    }

    public function heroMobileMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'hero_mobile_media_id');
    }

    /** @return array{url: string, alt_text: string, width: ?int, height: ?int}|null */
    public function resolvedCover(): ?array
    {
        $media = $this->coverMedia;
        $url = $media ? $media->deliveryUrl() : $this->cover_image_path;
        if (! $url) {
            return null;
        }

        $alt = $this->cover_alt_text ?: (
            $media?->alt_is_custom && filled($media->alt_text)
                ? $media->alt_text
                : MediaAltText::suggest($this->name)
        );

        return ['url' => $url, 'alt_text' => $alt, 'width' => $media?->width, 'height' => $media?->height];
    }

    public function modalities(): HasMany
    {
        return $this->hasMany(CourseModality::class);
    }

    public function faqs(): HasMany
    {
        return $this->hasMany(CourseFaq::class);
    }

    public function sharedFaqs(): BelongsToMany
    {
        return $this->belongsToMany(SharedFaq::class, 'course_shared_faq')->withPivot('sort_order');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }
}
