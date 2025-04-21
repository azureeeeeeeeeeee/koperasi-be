<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'fullname' => 'Test User 1',
            'email' => 'testuser@gmail.com',
            'password' => Hash::make('test321321321'),
            'tipe' => 'admin',
            'is_verified' => true,
            'status_keanggotaan' => 'bukan anggota',
        ]);

        User::factory()->create([
            'fullname' => 'User Pengguna',
            'email' => 'pengguna@staff.itk.ac.id',
            'password' => Hash::make('pengguna321321321'),
            'tipe' => 'pengguna',
            'is_verified' => true,
            'status_keanggotaan' => 'tidak aktif',
        ]);

        Category::create([
            'name' => 'makanan berat',
            'potongan' => 10.00,
        ]);

        Product::create([
            'name' => 'Mie gambus',
            'price' => 8000,
            'stock' => 10,
            'category_id' => 1,
            'user_id' => 1,
        ]);
    }
}
