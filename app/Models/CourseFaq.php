<?php

namespace App\Models;

use Database\Factories\CourseFaqFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseFaq extends Model
{
    /** @use HasFactory<CourseFaqFactory> */
    use HasFactory;

    protected $fillable = ['course_id', 'question', 'answer', 'sort_order'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
