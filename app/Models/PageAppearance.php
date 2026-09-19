<?php

namespace App\Models;

use Database\Factories\PageAppearanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageAppearance extends Model
{
    /** @use HasFactory<PageAppearanceFactory> */
    use HasFactory;

    protected $fillable = ['page_key', 'hero_enabled', 'hero_media_id', 'hero_mobile_media_id', 'hero_image_position', 'hero_overlay_preset', 'hero_overlay_opacity'];

    protected $attributes = [
        'hero_enabled' => false,
        'hero_image_position' => 'center',
        'hero_overlay_preset' => 'institutional',
        'hero_overlay_opacity' => 75,
    ];

    protected function casts(): array
    {
        return ['hero_enabled' => 'boolean', 'hero_overlay_opacity' => 'integer'];
    }

    public function heroMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'hero_media_id');
    }

    public function heroMobileMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'hero_mobile_media_id');
    }
}
