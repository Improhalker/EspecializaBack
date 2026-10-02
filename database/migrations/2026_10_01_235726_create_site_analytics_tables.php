<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_page_views', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('session_hash', 64);
            $table->string('path', 512);
            $table->string('device', 16);
            $table->string('referrer_host')->nullable();
            $table->string('utm_source', 80)->nullable();
            $table->string('utm_medium', 80)->nullable();
            $table->string('utm_campaign', 80)->nullable();
            $table->unsignedInteger('active_ms')->default(0);
            $table->unsignedTinyInteger('scroll_depth')->default(0);
            $table->unsignedInteger('lcp_ms')->nullable();
            $table->unsignedInteger('inp_ms')->nullable();
            $table->decimal('cls', 8, 4)->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('updated_at');
            $table->index('started_at');
            $table->index(['path', 'started_at']);
            $table->index(['session_hash', 'started_at']);
        });
        Schema::create('analytics_interactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('page_view_id')->constrained('analytics_page_views')->cascadeOnDelete();
            $table->string('kind', 24);
            $table->string('label', 120);
            $table->timestampTz('created_at');
            $table->index(['page_view_id', 'created_at']);
            $table->index(['kind', 'created_at']);
        });
        if (DB::getDriverName() === 'pgsql') {
            foreach (['analytics_page_views', 'analytics_interactions'] as $table) {
                DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
                foreach (['anon', 'authenticated'] as $role) {
                    if (DB::selectOne('SELECT 1 FROM pg_roles WHERE rolname = ?', [$role])) {
                        DB::statement("REVOKE ALL ON TABLE {$table} FROM {$role}");
                    }
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_interactions');
        Schema::dropIfExists('analytics_page_views');
    }
};
