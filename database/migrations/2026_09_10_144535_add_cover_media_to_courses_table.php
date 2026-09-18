<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->foreignId('cover_media_id')->nullable()->index()->constrained('media')->restrictOnDelete();
            $table->string('cover_alt_text', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cover_media_id');
            $table->dropColumn('cover_alt_text');
        });
    }
};
