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
     * Seed a local team (sign in as admin@batta.dev / password) and sample content.
     */
    public function run(): void
    {
        User::factory()->owner()->create([
            'name' => 'عبدالرحمن البطة',
            'email' => 'admin@batta.dev',
            'title' => 'مطوّر ويب ومدرّب',
        ]);

        User::factory()->role(Role::Editor)->create(['name' => 'سارة النجار', 'email' => 'sara@batta.dev']);
        User::factory()->role(Role::Support)->create(['name' => 'يوسف عودة', 'email' => 'yousef@batta.dev']);

        $this->call(ContentSeeder::class);
    }
}
