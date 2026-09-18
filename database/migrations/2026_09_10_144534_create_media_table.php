<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('upload_key')->nullable()->unique();
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('path', 1024);
            $table->string('disk', 30)->default('supabase');
            $table->string('bucket', 100);
            $table->string('original_mime_type', 100);
            $table->string('mime_type', 100);
            $table->string('original_extension', 10);
            $table->string('extension', 10);
            $table->unsignedBigInteger('original_size');
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('original_width')->nullable();
            $table->unsignedInteger('original_height')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->decimal('reduction_percent', 10, 2);
            $table->string('alt_text', 500)->nullable();
            $table->boolean('alt_is_custom')->default(false);
            $table->boolean('is_decorative')->default(false);
            $table->string('visibility', 20)->default('public');
            $table->string('status', 20)->default('ready');
            $table->timestamps();
            $table->index(['created_at', 'id']);
            $table->index(['original_name', 'id']);
            $table->index(['size', 'id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE media ENABLE ROW LEVEL SECURITY');
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
            $extension = DB::selectOne("SELECT n.nspname AS schema_name FROM pg_extension e JOIN pg_namespace n ON n.oid = e.extnamespace WHERE e.extname = 'pg_trgm'");
            $schema = str_replace('"', '""', $extension->schema_name);
            DB::statement('CREATE INDEX media_name_search_idx ON media USING gin (original_name "'.$schema.'".gin_trgm_ops)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
