<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class WorkflowSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            // Bawaan false (mati) agar sistem bersifat auto-approve (Solo Mode) saat baru instalasi
            ['key' => 'require_finance_approval', 'value' => 'false'],
            ['key' => 'require_inventory_approval', 'value' => 'false'],
            ['key' => 'require_purchase_approval', 'value' => 'false'],
        ];

        foreach ($settings as $setting) {
            Setting::firstOrCreate(['key' => $setting['key']], ['value' => $setting['value']]);
        }
    }
}
