<?php

namespace Database\Seeders;

use App\Models\Loket;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@disdukcapil.test'],
            [
                'name' => 'Admin Disdukcapil',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        // Equal-luminance trio (~0.15): white text clears 4.5:1 on each, and
        // each clears 3:1 as a frame against both the dark and light display.
        $services = [
            ['name' => 'KTP', 'prefix' => 'A', 'color' => '#2563eb', 'sort_order' => 1],
            ['name' => 'KK', 'prefix' => 'B', 'color' => '#047857', 'sort_order' => 2],
            ['name' => 'Akta', 'prefix' => 'C', 'color' => '#b45309', 'sort_order' => 3],
        ];

        foreach ($services as $data) {
            $service = Service::firstOrCreate(
                ['name' => $data['name']],
                [
                    'prefix' => $data['prefix'],
                    'color' => $data['color'],
                    'is_active' => true,
                    'sort_order' => $data['sort_order'],
                ]
            );

            if ($service->lokets()->doesntExist()) {
                Loket::create([
                    'service_id' => $service->id,
                    'name' => "Loket {$service->name} 1",
                    'officer_name' => 'Budi',
                    'is_active' => true,
                    'sort_order' => 1,
                ]);

                Loket::create([
                    'service_id' => $service->id,
                    'name' => "Loket {$service->name} 2",
                    'officer_name' => 'Siti',
                    'is_active' => true,
                    'sort_order' => 2,
                ]);
            }
        }

        $defaultSettings = [
            'app_name' => 'Antrian Disdukcapil',
            'primary_color' => '#2563eb',
            'accent_color' => '#16a34a',
            'running_text' => json_encode([\App\Support\Branding::DEFAULT_RUNNING_TEXT]),
        ];

        foreach ($defaultSettings as $key => $value) {
            if (Setting::get($key) === null) {
                Setting::set($key, $value);
            }
        }
    }
}
