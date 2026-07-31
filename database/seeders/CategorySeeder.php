<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run()
    {
        $categories = [
            ['name' => 'Electronics', 'description' => 'Electronic gadgets and devices.'],
            ['name' => 'Fashion', 'description' => 'Clothing and accessories.'],
            ['name' => 'Home & Garden', 'description' => 'Furniture and gardening tools.'],
            ['name' => 'Sports', 'description' => 'Sports equipment and accessories.'],
            ['name' => 'Books', 'description' => 'Books across various genres.'],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}
