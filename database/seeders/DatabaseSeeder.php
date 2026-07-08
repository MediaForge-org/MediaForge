<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Media\Library;
use App\Core\Support\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the three test users (admin/manager/member) and a demo library.
     * Idempotent so `db:seed` can be re-run safely.
     */
    public function run(): void
    {
        $users = [
            ['name' => 'Admin', 'email' => 'admin@mediaforge.test', 'role' => Role::Admin],
            ['name' => 'Manager', 'email' => 'manager@mediaforge.test', 'role' => Role::Manager],
            ['name' => 'Member', 'email' => 'member@mediaforge.test', 'role' => Role::Member],
        ];

        foreach ($users as $user) {
            User::query()->firstOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'role' => $user['role'],
                    'password_hash' => Hash::make('password'),
                    'theme_preference' => 'system',
                ],
            );
        }

        Library::query()->firstOrCreate(
            ['root_path' => '/media/movies'],
            ['name' => 'Movies', 'media_kind' => 'video'],
        );
    }
}
