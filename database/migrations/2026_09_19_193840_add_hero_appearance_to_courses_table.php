<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->boolean('hero_enabled')->default(false);
            $table->foreignId('hero_media_id')->nullable()->index()->constrained('media')->restrictOnDelete();
            $table->foreignId('hero_mobile_media_id')->nullable()->index()->constrained('media')->restrictOnDelete();
            $table->string('hero_image_position', 20)->default('center');
            $table->string('hero_overlay_preset', 30)->default('institutional');
            $table->unsignedTinyInteger('hero_overlay_opacity')->default(75);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('hero_mobile_media_id');
            $table->dropConstrainedForeignId('hero_media_id');
            $table->dropColumn(['hero_enabled', 'hero_image_position', 'hero_overlay_preset', 'hero_overlay_opacity']);
        });
    }
};
