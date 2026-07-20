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

        $services = [
            ['name' => 'KTP', 'prefix' => 'A', 'color' => '#2563eb', 'sort_order' => 1],
            ['name' => 'KK', 'prefix' => 'B', 'color' => '#16a34a', 'sort_order' => 2],
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
            'running_text' => 'Selamat datang di Dinas Kependudukan dan Pencatatan Sipil',
        ];

        foreach ($defaultSettings as $key => $value) {
            if (Setting::get($key) === null) {
                Setting::set($key, $value);
            }
        }
    }
}
