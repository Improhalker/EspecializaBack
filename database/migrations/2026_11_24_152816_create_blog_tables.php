<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 160);
            $table->string('slug', 160)->unique();
            $table->string('excerpt', 320);
            $table->longText('body');
            $table->string('category', 60)->nullable()->index();
            $table->foreignId('course_id')->nullable()->index()->constrained('courses')->nullOnDelete();
            $table->string('cta_action', 20)->default('course');
            $table->string('cta_label', 80)->nullable();
            $table->foreignId('cover_media_id')->nullable()->index()->constrained('media')->restrictOnDelete();
            $table->string('cover_alt_text', 500)->nullable();
            $table->string('meta_title', 160)->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'published_at', 'id']);
        });

        Schema::create('blog_post_media', function (Blueprint $table): void {
            $table->foreignId('blog_post_id')->constrained('blog_posts')->cascadeOnDelete();
            $table->foreignId('media_id')->index()->constrained('media')->restrictOnDelete();
            $table->primary(['blog_post_id', 'media_id']);
        });

        Schema::create('blog_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('blog_post_id')->constrained('blog_posts')->cascadeOnDelete();
            $table->string('author_name', 60);
            $table->text('body');
            $table->string('status', 20)->default('pending');
            $table->timestamp('moderated_at')->nullable();
            $table->timestamps();
            $table->index(['blog_post_id', 'status', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            foreach (['blog_posts', 'blog_post_media', 'blog_comments'] as $table) {
                DB::statement("ALTER TABLE public.{$table} ENABLE ROW LEVEL SECURITY");
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_comments');
        Schema::dropIfExists('blog_post_media');
        Schema::dropIfExists('blog_posts');
    }
};
