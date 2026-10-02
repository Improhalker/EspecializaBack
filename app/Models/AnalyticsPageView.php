<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnalyticsPageView extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'session_hash', 'path', 'device', 'referrer_host', 'utm_source', 'utm_medium', 'utm_campaign', 'active_ms', 'scroll_depth', 'lcp_ms', 'inp_ms', 'cls', 'started_at', 'updated_at'];

    protected $hidden = ['session_hash'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'updated_at' => 'datetime', 'active_ms' => 'integer', 'scroll_depth' => 'integer', 'lcp_ms' => 'integer', 'inp_ms' => 'integer', 'cls' => 'float'];
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(AnalyticsInteraction::class, 'page_view_id');
    }
}
