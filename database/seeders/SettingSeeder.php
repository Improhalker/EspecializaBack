<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Setting::query()->updateOrCreate(
            ['key' => 'contact'],
            ['value' => ['whatsapp_number' => '5519999065094']],
        );
    }
}
