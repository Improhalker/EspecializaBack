<?php

namespace App\Models;

use Database\Factories\BlogCommentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlogComment extends Model
{
    /** @use HasFactory<BlogCommentFactory> */
    use HasFactory;

    public const STATUSES = ['pending', 'approved', 'rejected', 'hidden'];

    /** Status is intentionally not mass assignable: only moderation may change it. */
    protected $fillable = ['author_name', 'body'];

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return ['moderated_at' => 'datetime'];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class, 'blog_post_id');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }
}
