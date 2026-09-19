<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('page_appearances', function (Blueprint $table): void {
            $table->id();
            $table->string('page_key', 80)->unique();
            $table->boolean('hero_enabled')->default(false);
            $table->foreignId('hero_media_id')->nullable()->index()->constrained('media')->restrictOnDelete();
            $table->foreignId('hero_mobile_media_id')->nullable()->index()->constrained('media')->restrictOnDelete();
            $table->string('hero_image_position', 20)->default('center');
            $table->string('hero_overlay_preset', 30)->default('institutional');
            $table->unsignedTinyInteger('hero_overlay_opacity')->default(75);
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE public.page_appearances ENABLE ROW LEVEL SECURITY');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_appearances');
    }
};
