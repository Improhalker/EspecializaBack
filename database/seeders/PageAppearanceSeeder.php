<?php

namespace Database\Seeders;

use App\Models\PageAppearance;
use Illuminate\Database\Seeder;

class PageAppearanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (config('page_appearances.pages', []) as $key => $definition) {
            if ($definition['supports_hero'] ?? false) {
                PageAppearance::query()->firstOrCreate(['page_key' => $key]);
            }
        }
    }
}
