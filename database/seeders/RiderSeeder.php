<?php

namespace Database\Seeders;

use App\Models\Rider;
use Illuminate\Database\Seeder;

class RiderSeeder extends Seeder
{
    public function run(): void
    {
        $riders = [
            ['name' => 'Kevin Otieno',   'email' => 'kevin.otieno@swiftcart.co.ke',   'phone' => '0712345601', 'vehicle' => 'Motorbike', 'status' => 'Available'],
            ['name' => 'Brian Mwangi',   'email' => 'brian.mwangi@swiftcart.co.ke',   'phone' => '0712345602', 'vehicle' => 'Motorbike', 'status' => 'Available'],
            ['name' => 'Faith Wanjiru',  'email' => 'faith.wanjiru@swiftcart.co.ke',  'phone' => '0712345603', 'vehicle' => 'Motorbike', 'status' => 'Busy'],
            ['name' => 'Dennis Kiptoo',  'email' => 'dennis.kiptoo@swiftcart.co.ke',  'phone' => '0712345604', 'vehicle' => 'Car',       'status' => 'Available'],
            ['name' => 'Grace Achieng',  'email' => 'grace.achieng@swiftcart.co.ke',  'phone' => '0712345605', 'vehicle' => 'Bicycle',   'status' => 'Available'],
            ['name' => 'Samuel Kamau',   'email' => 'samuel.kamau@swiftcart.co.ke',   'phone' => '0712345606', 'vehicle' => 'Motorbike', 'status' => 'Offline'],
            ['name' => 'Mercy Chebet',   'email' => 'mercy.chebet@swiftcart.co.ke',   'phone' => '0712345607', 'vehicle' => 'Motorbike', 'status' => 'Busy'],
            ['name' => 'Peter Njoroge',  'email' => 'peter.njoroge@swiftcart.co.ke',  'phone' => '0712345608', 'vehicle' => 'Car',       'status' => 'Available'],
            ['name' => 'Lucy Nyambura',  'email' => 'lucy.nyambura@swiftcart.co.ke',  'phone' => '0712345609', 'vehicle' => 'Bicycle',   'status' => 'Offline'],
            ['name' => 'James Odhiambo', 'email' => 'james.odhiambo@swiftcart.co.ke', 'phone' => '0712345610', 'vehicle' => 'Motorbike', 'status' => 'Available'],
        ];

        foreach ($riders as $rider) {
            Rider::updateOrCreate(
                ['phone' => $rider['phone']], // idempotent — re-running the seeder won't duplicate
                $rider
            );
        }
    }
}