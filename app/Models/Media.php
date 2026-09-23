<?php

namespace App\Models;

use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory;

    protected $table = 'media';

    protected $fillable = [
        'uuid', 'upload_key', 'original_name', 'stored_name', 'path', 'disk', 'bucket',
        'original_mime_type', 'mime_type', 'original_extension', 'extension',
        'original_size', 'size', 'original_width', 'original_height', 'width', 'height',
        'reduction_percent', 'alt_text', 'alt_is_custom', 'is_decorative', 'visibility', 'status',
    ];

    protected $hidden = ['path', 'bucket', 'disk', 'upload_key'];

    protected static function booted(): void
    {
        static::creating(function (Media $media): void {
            $media->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'original_size' => 'integer', 'size' => 'integer', 'width' => 'integer', 'height' => 'integer',
            'original_width' => 'integer', 'original_height' => 'integer', 'reduction_percent' => 'float',
            'alt_is_custom' => 'boolean', 'is_decorative' => 'boolean',
        ];
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, 'cover_media_id');
    }

    public function heroCourses(): HasMany
    {
        return $this->hasMany(Course::class, 'hero_media_id');
    }

    public function mobileHeroCourses(): HasMany
    {
        return $this->hasMany(Course::class, 'hero_mobile_media_id');
    }

    public function pageHeroes(): HasMany
    {
        return $this->hasMany(PageAppearance::class, 'hero_media_id');
    }

    public function mobilePageHeroes(): HasMany
    {
        return $this->hasMany(PageAppearance::class, 'hero_mobile_media_id');
    }

    public function testimonialAvatars(): HasMany
    {
        return $this->hasMany(Testimonial::class, 'avatar_media_id');
    }

    public function deliveryUrl(): ?string
    {
        return $this->status === 'ready' ? route('media.delivery', ['media' => $this->uuid]) : null;
    }
}
