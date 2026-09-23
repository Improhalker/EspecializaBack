<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testimonials', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('content');
            $table->unsignedTinyInteger('rating');
            $table->foreignId('avatar_media_id')->nullable()->index()->constrained('media')->restrictOnDelete();
            $table->foreignId('course_id')->nullable()->index()->constrained('courses')->nullOnDelete();
            $table->boolean('is_published')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE public.testimonials ENABLE ROW LEVEL SECURITY');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonials');
    }
};
