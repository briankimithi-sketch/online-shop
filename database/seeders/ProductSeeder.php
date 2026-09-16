<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            ['name' => 'Wireless Headphones', 'price' => 4500, 'description' => 'High-quality wireless headphones with noise cancellation and 30-hour battery life. Features premium sound quality, comfortable over-ear design, and Bluetooth 5.0 connectivity.', 'category' => 'Electronics', 'image' => '/images/4.jpg'],
            ['name' => 'Running Shoes', 'price' => 17000, 'description' => 'Comfortable running shoes designed for all terrains with superior cushioning. Lightweight mesh upper with responsive midsole for maximum energy return.', 'category' => 'Sports', 'image' => '/images/11.jpg'],
            ['name' => 'Smart Watch', 'price' => 4800, 'description' => 'Feature-rich smartwatch with health tracking, GPS, and 7-day battery life. Includes heart rate monitor, sleep tracking, and water resistance.', 'category' => 'Electronics', 'image' => '/images/7.jpg'],
            ['name' => 'Backpack', 'price' => 6500, 'description' => 'Durable and stylish backpack perfect for school, work, or travel. Features padded laptop compartment, multiple pockets, and water-resistant material.', 'category' => 'Clothing', 'image' => '/images/3.jpg'],
            ['name' => 'Polarized Sunglasses', 'price' => 11500, 'description' => 'Polarized sunglasses with UV protection and lightweight frame. Perfect for outdoor activities with scratch-resistant lenses.', 'category' => 'Accessories', 'image' => '/images/10.jpg'],
            ['name' => 'Coffee Maker', 'price' => 19000, 'description' => 'Programmable coffee maker with built-in grinder for the perfect brew every morning. Makes up to 12 cups with auto-shutoff safety feature.', 'category' => 'Home & Garden', 'image' => '/images/6.jpg'],
            ['name' => 'Yoga Mat', 'price' => 5000, 'description' => 'Non-slip yoga mat with carrying strap for comfortable workouts. Extra thick 6mm cushioning for joint support.', 'category' => 'Sports', 'image' => '/images/2.jpg'],
            ['name' => 'Leather Wallet', 'price' => 7500, 'description' => 'Genuine leather wallet with RFID blocking technology. Multiple card slots, ID window, and slim profile design.', 'category' => 'Accessories', 'image' => '/images/9.jpg'],
            ['name' => 'Bluetooth Speaker', 'price' => 3500, 'description' => 'Portable Bluetooth speaker with 360-degree sound and waterproof design. 20-hour battery life with built-in microphone for calls.', 'category' => 'Electronics', 'image' => '/images/5.jpg'],
            ['name' => 'Fitness Tracker', 'price' => 4200, 'description' => 'Advanced fitness tracker with heart rate monitor and sleep tracking. Waterproof design with 14-day battery life.', 'category' => 'Electronics', 'image' => '/images/1.jpg'],
            ['name' => 'Wireless Earbuds', 'price' => 3900, 'description' => 'Compact wireless earbuds with premium sound quality and charging case. Active noise cancellation with touch controls.', 'category' => 'Electronics', 'image' => '/images/8.jpg'],
        ];

        foreach ($products as $product) {
            Product::create($product);
        }
    }
}
