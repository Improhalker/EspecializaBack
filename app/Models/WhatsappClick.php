<?php

namespace App\Models;

use Database\Factories\WhatsappClickFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappClick extends Model
{
    /** @use HasFactory<WhatsappClickFactory> */
    use HasFactory;

    protected $fillable = ['course_id', 'course_modality_id', 'source_url', 'utm_source', 'utm_medium', 'utm_campaign'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function modality(): BelongsTo
    {
        return $this->belongsTo(CourseModality::class, 'course_modality_id');
    }
}
