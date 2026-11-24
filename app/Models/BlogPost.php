<?php

namespace App\Models;

use Database\Factories\BlogPostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BlogPost extends Model
{
    /** @use HasFactory<BlogPostFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_UNPUBLISHED = 'unpublished';

    public const CTA_ACTIONS = ['course', 'whatsapp'];

    protected $fillable = [
        'title', 'slug', 'excerpt', 'body', 'category', 'course_id', 'cta_action', 'cta_label',
        'cover_media_id', 'cover_alt_text', 'meta_title', 'meta_description', 'status', 'published_at',
    ];

    protected $attributes = ['status' => self::STATUS_DRAFT, 'cta_action' => 'course'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function coverMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_media_id');
    }

    /** Images from the media library embedded in the article body. */
    public function bodyMedia(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'blog_post_media');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(BlogComment::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED)->whereNotNull('published_at');
    }

    /** Listing order: most recent publication first, id as a stable tie-breaker. */
    public function scopeInListingOrder(Builder $query): Builder
    {
        return $query->orderByDesc('published_at')->orderByDesc('id');
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED && $this->published_at !== null;
    }

    /** @return array{url: string, alt_text: string, width: ?int, height: ?int}|null */
    public function resolvedCover(): ?array
    {
        $media = $this->coverMedia;
        $url = $media?->deliveryUrl();
        if (! $url) {
            return null;
        }

        $alt = $this->cover_alt_text ?: ($media->alt_is_custom && filled($media->alt_text) ? $media->alt_text : $this->title);

        return ['url' => $url, 'alt_text' => $alt, 'width' => $media->width, 'height' => $media->height];
    }

    /** Uses the part of the course name before a dash, e.g. "MOPP — Transporte de Produtos Perigosos" becomes "MOPP". */
    public function defaultCtaLabel(Course $course): string
    {
        $name = trim(preg_split('/\s+[—–-]\s+/u', $course->name)[0] ?? '') ?: $course->name;

        return $this->cta_action === 'whatsapp' ? 'Tire dúvidas sobre o curso de '.$name : 'Conheça o curso de '.$name;
    }

    public function readingMinutes(): int
    {
        $words = str_word_count(strip_tags((string) $this->body), 0, 'áàâãéêíóôõúüçÁÀÂÃÉÊÍÓÔÕÚÜÇ');

        return max(1, (int) ceil($words / 200));
    }
}
