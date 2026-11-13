<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\Facebook\DemoWorkspaceSeederService;
use App\Services\Social\MultiPlatformSeederService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(
        DemoWorkspaceSeederService $demoSeeder,
        MultiPlatformSeederService $multiPlatformSeeder,
    ): void {
        $user = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => 'password',
                'email_verified_at' => now(),
            ]
        );

        $demoSeeder->seedForUser($user);
        $multiPlatformSeeder->seedWorkspaceForUser($user);
    }
}
