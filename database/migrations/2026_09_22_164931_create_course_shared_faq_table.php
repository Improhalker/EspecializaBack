<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_shared_faq', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shared_faq_id')->constrained('shared_faqs')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->nullable();
            $table->timestamps();
            $table->unique(['course_id', 'shared_faq_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE public.course_shared_faq ENABLE ROW LEVEL SECURITY');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('course_shared_faq');
    }
};
