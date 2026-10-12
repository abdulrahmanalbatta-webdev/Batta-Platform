<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed a local team (sign in as admin@batta.dev / password) sample content, students and their registrations.
     */
    public function run(): void
    {
        // sample accounts with a known password never go into a live database
        if (app()->isProduction()) {
            $this->command?->error('The sample data is for local use only; create the owner with app:create-owner.');

            return;
        }

        User::factory()->owner()->create([
            'name' => 'عبد الرحمن البطه',
            'email' => 'admin@batta.dev',
            'title' => 'مطوّر ويب ومدرّب',
        ]);

        User::factory()->role(Role::Editor)->create(['name' => 'سارة النجار', 'email' => 'sara@batta.dev']);
        User::factory()->role(Role::Support)->create(['name' => 'يوسف عودة', 'email' => 'yousef@batta.dev']);

        $this->call([ContentSeeder::class, StudentSeeder::class, CommunitySeeder::class, ActivitySeeder::class]);
    }
}
