<?php

namespace App\Models;

use Database\Factories\CourseModalityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseModality extends Model
{
    /** @use HasFactory<CourseModalityFactory> */
    use HasFactory;

    protected $fillable = ['course_id', 'name', 'workload', 'description', 'features', 'bonuses', 'price_mode', 'price', 'price_label', 'whatsapp_message', 'sort_order', 'is_published'];

    protected function casts(): array
    {
        return ['features' => 'array', 'bonuses' => 'array', 'price' => 'decimal:2', 'is_published' => 'boolean'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }
}
