<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Menu;
use App\Models\MenuPrice;
use App\Models\Outlet;
use Illuminate\Support\Str;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $outlets = Outlet::where('is_active', true)->get();
        
        $categories = [
            'Makanan' => ['Nasi Goreng', 'Mie Goreng', 'Ayam Goreng', 'Sate Ayam', 'Soto Ayam'],
            'Minuman' => ['Es Teh', 'Es Jeruk', 'Kopi', 'Teh Hangat', 'Jus Alpukat'],
            'Snack' => ['Kentang Goreng', 'Onion Ring', 'Nugget', 'Sosis'],
            'Paket Hemat' => ['Paket Nasi Goreng', 'Paket Mie Goreng', 'Paket Ayam Goreng'],
        ];
        
        foreach ($categories as $catName => $menus) {
            $category = Category::firstOrCreate(
                ['name' => $catName],
                ['sort_order' => 0]
            );
            
            foreach ($menus as $index => $menuName) {
                $menu = Menu::firstOrCreate(
                    ['name' => $menuName],
                    [
                        'category_id' => $category->id,
                        'description' => "Deskripsi $menuName",
                        'is_active' => true,
                    ]
                );
                
                foreach ($outlets as $outlet) {
                    MenuPrice::firstOrCreate(
                        [
                            'menu_id' => $menu->id,
                            'outlet_id' => $outlet->id,
                        ],
                        [
                            'price' => rand(15000, 50000),
                            'is_active' => true,
                        ]
                    );
                }
            }
        }
    }
}