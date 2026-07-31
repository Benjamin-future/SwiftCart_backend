<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Product;
use App\Models\Category;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure categories exist (id lookup map)
        $catNames = [
            'electronics'   => 'Electronics',
            'fashion'       => 'Fashion',
            'home-garden'   => 'Home & Garden',
            'sports'        => 'Sports',
            'books'         => 'Books',
        ];

        $catIds = [];
        foreach ($catNames as $slug => $name) {
            $cat = Category::firstOrCreate(
                ['name' => $name],
                ['slug' => method_exists(Category::class, 'getFillable') ? Str::slug($name) : $slug]
            );
            $catIds[$slug] = $cat->id;
        }

        // Products (title is used as a unique key)
        // 'stock' is always >= 5 here — once orders actually decrement
        // it down to 0 (see Product::decrementStock()), the storefront
        // will correctly flip that specific product to "Out of Stock"
        // without every other product being affected.
        $products = [
            // -------------------- Electronics --------------------
            [
                'title'       => 'Wireless Bluetooth Headphones',
                'description' => 'High-quality wireless headphones with noise cancellation',
                'price'       => 2999,
                'image'       => '/images/bluetooth_headphones.jpg',
                'category'    => 'electronics',
                'stock'       => 25,
            ],
            [
                'title'       => 'Smartphone Protective Case',
                'description' => 'Durable protective phone case with shock absorption',
                'price'       => 499,
                'image'       => '/images/phone-case.jpg',
                'category'    => 'electronics',
                'stock'       => 60,
            ],
            [
                'title'       => '32" Smart TV',
                'description' => 'Full HD Smart TV with streaming apps built-in',
                'price'       => 17999,
                'image'       => '/images/tv.jpg',
                'category'    => 'electronics',
                'stock'       => 8,
            ],
            [
                'title'       => 'Smartphone XYZ',
                'description' => 'Feature-packed smartphone with great performance and battery life',
                'price'       => 24999,
                'image'       => '/images/smartphone_xyz.jpg',
                'category'    => 'electronics',
                'stock'       => 12,
            ],
            [
                'title'       => 'USB-C Laptop Charger 65W',
                'description' => 'Fast, safe charging for compatible laptops and tablets',
                'price'       => 3499,
                'image'       => '/images/typeC.jpg',
                'category'    => 'electronics',
                'stock'       => 35,
            ],
            [
                'title'       => 'Power Bank 20,000mAh',
                'description' => 'High-capacity portable charger with dual USB output',
                'price'       => 3299,
                'image'       => '/images/powerbank.jpeg',
                'category'    => 'electronics',
                'stock'       => 20,
            ],
            [
                'title'       => 'Wireless Mouse',
                'description' => 'Ergonomic 2.4GHz wireless mouse with silent clicks',
                'price'       => 899,
                'image'       => '/images/wireless.jpeg',
                'category'    => 'electronics',
                'stock'       => 45,
            ],

            // -------------------- Fashion --------------------
            [
                'title'       => 'Men Sneakers',
                'description' => 'Comfortable everyday sneakers with a clean look',
                'price'       => 2499,
                'image'       => '/images/men_sneakers.jpg',
                'category'    => 'fashion',
                'stock'       => 18,
            ],
            [
                'title'       => 'Classic Cotton T-Shirt',
                'description' => 'Premium 100% cotton tee — breathable and durable',
                'price'       => 799,
                'image'       => '/images/tshirt.jpeg',
                'category'    => 'fashion',
                'stock'       => 50,
            ],
            [
                'title'       => 'Denim Jacket',
                'description' => 'Timeless denim jacket with a modern fit',
                'price'       => 3499,
                'image'       => '/images/denim.jpeg',
                'category'    => 'fashion',
                'stock'       => 15,
            ],
            [
                'title'       => 'Women’s Handbag',
                'description' => 'Elegant everyday handbag with multiple compartments',
                'price'       => 4199,
                'image'       => '/images/handbag.jpeg',
                'category'    => 'fashion',
                'stock'       => 10,
            ],
            [
                'title'       => 'Sports Cap',
                'description' => 'Lightweight adjustable cap with breathable mesh',
                'price'       => 599,
                'image'       => '/images/cap.png',
                'category'    => 'fashion',
                'stock'       => 40,
            ],

            // -------------------- Home & Garden --------------------
            [
                'title'       => 'Laptop Backpack',
                'description' => 'Waterproof backpack with laptop compartment',
                'price'       => 2499,
                'image'       => '/images/backpack.jpg',
                'category'    => 'home-garden',
                'stock'       => 22,
            ],
            [
                'title'       => 'Cooking Pot Set',
                'description' => 'Durable non-stick cooking pots ideal for family meals',
                'price'       => 5999,
                'image'       => '/images/cooking_pot_set.jpg',
                'category'    => 'home-garden',
                'stock'       => 9,
            ],
            [
                'title'       => 'Memory Foam Pillow',
                'description' => 'Neck-support pillow for deeper, more comfortable sleep',
                'price'       => 1999,
                'image'       => '/images/pillow.jpeg',
                'category'    => 'home-garden',
                'stock'       => 30,
            ],
            [
                'title'       => '7-Piece Knife Set',
                'description' => 'Chef-grade stainless steel knife set with wooden block',
                'price'       => 4599,
                'image'       => '/images/knife.jpeg',
                'category'    => 'home-garden',
                'stock'       => 7,
            ],
            [
                'title'       => 'LED Desk Lamp',
                'description' => 'Dimmable desk lamp with touch controls and USB port',
                'price'       => 1799,
                'image'       => '/images/lamp.jpg',
                'category'    => 'home-garden',
                'stock'       => 26,
            ],
            [
                'title'       => 'Plush Throw Blanket',
                'description' => 'Ultra-soft, cozy throw for couch or bed',
                'price'       => 1499,
                'image'       => '/images/plush.jpeg',
                'category'    => 'home-garden',
                'stock'       => 33,
            ],

            // -------------------- Sports --------------------
            [
                'title'       => 'Football Size 5',
                'description' => 'Match-quality PU football designed for durability',
                'price'       => 1799,
                'image'       => '/images/ball.jpg',
                'category'    => 'sports',
                'stock'       => 28,
            ],
            [
                'title'       => 'Yoga Mat (6mm)',
                'description' => 'Non-slip yoga mat for home workouts and stretching',
                'price'       => 1599,
                'image'       => '/images/yoga.jpg',
                'category'    => 'sports',
                'stock'       => 24,
            ],
            [
                'title'       => 'Adjustable Dumbbell Pair (2×10kg)',
                'description' => 'Space-saving dumbbells for strength training',
                'price'       => 8999,
                'image'       => '/images/dumbbell.jpg',
                'category'    => 'sports',
                'stock'       => 6,
            ],
            [
                'title'       => 'Skipping Rope',
                'description' => 'Tangle-free speed rope for cardio training',
                'price'       => 499,
                'image'       => '/images/skippingrope.jpg',
                'category'    => 'sports',
                'stock'       => 55,
            ],

            // -------------------- Books --------------------
            [
                'title'       => 'Learn Laravel in 24 Hours',
                'description' => 'Beginner-friendly guide to building apps with Laravel',
                'price'       => 1799,
                'image'       => '/images/laravel.jpg',
                'category'    => 'books',
                'stock'       => 16,
            ],
            [
                'title'       => 'Python 3 Essentials',
                'description' => 'Hands-on introduction to modern Vue development',
                'price'       => 1899,
                'image'       => '/images/python.jpg',
                'category'    => 'books',
                'stock'       => 19,
            ],
            [
                'title'       => 'Clean Code (Paperback)',
                'description' => 'A handbook of agile software craftsmanship',
                'price'       => 2599,
                'image'       => '/images/cleancode.jpeg',
                'category'    => 'books',
                'stock'       => 11,
            ],
            [
                'title'       => 'Design Patterns Illustrated',
                'description' => 'Visual guide to classic software design patterns',
                'price'       => 2399,
                'image'       => '/images/designpatterns.jpg',
                'category'    => 'books',
                'stock'       => 14,
            ],
        ];

        foreach ($products as $p) {
            $categoryId = $catIds[$p['category']] ?? null;

            Product::updateOrCreate(
                ['title' => $p['title']], // unique key to prevent duplicates
                [
                    'description' => $p['description'],
                    'price'       => $p['price'],
                    'image'       => $p['image'] ?? null,
                    'category_id' => $categoryId, // link to category
                    'stock'       => $p['stock'],
                ]
            );
        }
    }
}
