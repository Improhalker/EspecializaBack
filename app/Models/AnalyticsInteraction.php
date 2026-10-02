<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsInteraction extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'page_view_id', 'kind', 'label', 'created_at'];

    public function pageView(): BelongsTo
    {
        return $this->belongsTo(AnalyticsPageView::class, 'page_view_id');
    }
}
