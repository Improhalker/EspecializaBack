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
        Schema::create('course_modalities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('workload', 80)->nullable();
            $table->text('description')->nullable();
            $table->json('features')->nullable();
            $table->json('bonuses')->nullable();
            $table->string('price_mode')->default('hidden');
            $table->decimal('price', 10, 2)->nullable();
            $table->string('price_label')->nullable();
            $table->text('whatsapp_message')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_modalities');
    }
};
