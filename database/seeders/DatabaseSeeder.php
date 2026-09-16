<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            ProductSeeder::class,
        ]);

        Role::create([
            'name' => 'Admin',
        ]);
        Role::create([
            'name' => 'user',
        ]);
        User::create([
            'name' => 'Admin',
            'email' => 'admin@onlineshop.com',
            'phone' => '0720000222',
            'delivery_address' => 'N/A',
            'role_id' => 1,
            'password' => Hash::make('Qwerty1234'),
        ]);
        User::create([
            'name' => 'Brayo',
            'email' => 'brayo@onlineshop.com',
            'phone' => '0700000000',
            'delivery_address' => 'N/A',
            'role_id' => 2,
            'password' => Hash::make('yobi1234'),
        ]);
    }
}
