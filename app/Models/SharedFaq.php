<?php

namespace App\Models;

use Database\Factories\SharedFaqFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SharedFaq extends Model
{
    /** @use HasFactory<SharedFaqFactory> */
    use HasFactory;

    public const APPLICATION_ALL_COURSES = 'all_courses';

    public const APPLICATION_SELECTED_COURSES = 'selected_courses';

    protected $fillable = ['question', 'answer', 'application_mode', 'is_published', 'sort_order'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'sort_order' => 'integer'];
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_shared_faq')->withPivot('sort_order');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeApplicableTo(Builder $query, int $courseId): Builder
    {
        return $query->where(function (Builder $query) use ($courseId): void {
            $query->where('application_mode', self::APPLICATION_ALL_COURSES)
                ->orWhereHas('courses', fn (Builder $query) => $query->whereKey($courseId));
        });
    }
}
