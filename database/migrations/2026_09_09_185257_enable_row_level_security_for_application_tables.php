<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (['categories', 'courses', 'course_modalities', 'course_faqs', 'settings', 'whatsapp_clicks'] as $table) {
            DB::statement("ALTER TABLE public.{$table} ENABLE ROW LEVEL SECURITY");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (['categories', 'courses', 'course_modalities', 'course_faqs', 'settings', 'whatsapp_clicks'] as $table) {
            DB::statement("ALTER TABLE public.{$table} DISABLE ROW LEVEL SECURITY");
        }
    }
};
