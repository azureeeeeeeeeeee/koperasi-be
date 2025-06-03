<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Config;
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
        $products = [
            ['name' => 'Nasi Goreng Spesial', 'price' => 15000, 'stock' => 20, 'category_id' => 1],
            ['name' => 'Ayam Geprek', 'price' => 18000, 'stock' => 15, 'category_id' => 1],
            ['name' => 'Burger Mini', 'price' => 10000, 'stock' => 30, 'category_id' => 2],
            ['name' => 'Kue Cubit', 'price' => 5000, 'stock' => 25, 'category_id' => 2],
            ['name' => 'Keripik Pedas', 'price' => 7000, 'stock' => 40, 'category_id' => 2],
            ['name' => 'Es Teh Manis', 'price' => 5000, 'stock' => 50, 'category_id' => 3],
            ['name' => 'Kopi Susu', 'price' => 12000, 'stock' => 35, 'category_id' => 3],
            ['name' => 'Jus Alpukat', 'price' => 15000, 'stock' => 20, 'category_id' => 3],
            ['name' => 'Mie Ayam', 'price' => 13000, 'stock' => 18, 'category_id' => 1],
            ['name' => 'Soto Ayam', 'price' => 14000, 'stock' => 12, 'category_id' => 1],
            ['name' => 'Tempe Mendoan', 'price' => 6000, 'stock' => 30, 'category_id' => 2],
            ['name' => 'Bolu Kukus', 'price' => 4000, 'stock' => 20, 'category_id' => 2],
            ['name' => 'Air Mineral', 'price' => 3000, 'stock' => 60, 'category_id' => 3],
            ['name' => 'Thai Tea', 'price' => 10000, 'stock' => 25, 'category_id' => 3],
            ['name' => 'Roti Bakar', 'price' => 9000, 'stock' => 22, 'category_id' => 2],
            ['name' => 'Bakso', 'price' => 16000, 'stock' => 17, 'category_id' => 1],
            ['name' => 'Pisang Goreng', 'price' => 5000, 'stock' => 28, 'category_id' => 2],
            ['name' => 'Sate Ayam', 'price' => 17000, 'stock' => 15, 'category_id' => 1],
            ['name' => 'Lemon Tea', 'price' => 7000, 'stock' => 30, 'category_id' => 3],
        ];

        User::factory()->create([
            'fullname' => 'Test User 1',
            'email' => 'testuser@gmail.com',
            'password' => Hash::make('test321321321'),
            'tipe' => 'admin',
            // 'is_verified' => true,
            'status_keanggotaan' => 'bukan anggota',
        ]);

        User::factory()->create([
            'fullname' => 'User Pengguna',
            'email' => 'pengguna@staff.itk.ac.id',
            'password' => Hash::make('pengguna321321321'),
            'tipe' => 'pengguna',
            // 'is_verified' => true,
            'status_keanggotaan' => 'tidak aktif',
        ]);

        User::factory()->create([
            'fullname' => 'Tatak Adi',
            'email' => 'tatakadi@staff.itk.ac.id',
            'password' => Hash::make('tatak321321321'),
            'tipe' => 'pengguna',
            // 'is_verified' => true,
            'status_keanggotaan' => 'aktif',
        ]);

        User::factory()->create([
            'fullname' => 'Pegawai',
            'email' => 'pegawai@gmail.com',
            'password' => Hash::make('pegawai321321321'),
            'tipe' => 'pegawai',
            // 'is_verified' => true,
            'status_keanggotaan' => 'bukan anggota',
        ]);

        Category::create([
            'name' => 'makanan berat',
            'potongan' => 10.00,
            'keuntungan' => 2000,
        ]);

        Category::create([
            'name' => 'makanan ringan',
            'potongan' => 10.00,
            'keuntungan' => 2000,
        ]);

        Category::create([
            'name' => 'minuman',
            'potongan' => 5.00,
            'keuntungan' => 2000,
        ]);

        foreach ($products as $product) {
            Product::create([
                'name' => $product['name'],
                'price' => $product['price'],
                'stock' => $product['stock'],
                'category_id' => $product['category_id'],
                'user_id' => 1,
                'image_url' => 'https://placehold.co/600x400',
            ]);
        }

        Config::create([
            'key' => 'iuran wajib',
            'key2'=> 24,
            'value' => '30000',
        ]);
    }
}
