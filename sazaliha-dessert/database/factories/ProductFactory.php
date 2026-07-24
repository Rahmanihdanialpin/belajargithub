<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    protected $model = \App\Models\Product::class;

    public function definition(): array
    {
        $desserts = [
            'Strawberry Shortcake', 'Chocolate Lava Cake', 'Vanilla Cupcake', 'Red Velvet Cake',
            'Matcha Cheesecake', 'Tiramisu', 'Blueberry Muffin', 'Lemon Tart',
            'Oreo Cheesecake', 'Caramel Pudding', 'Choco Chip Cookies', 'Rainbow Macaron',
            'Glazed Donut', 'Mango Pudding', 'Coffee Brownies', 'Pandan Cake',
            'Butter Cookies', 'Rose Lychee Cake', 'Salted Caramel Ice Cream', 'Mochi Ice Cream'
        ];

        $name = fake()->unique()->randomElement($desserts);
        $price = fake()->numberBetween(15000, 150000);
        $cost = (int) ($price * fake()->randomFloat(2, 0.4, 0.7));

        return [
            'category_id' => Category::inRandomOrder()->first()->id ?? 1,
            'name' => $name,
            'slug' => Str::slug($name) . '-' . fake()->unique()->randomNumber(4),
            'description' => fake()->paragraph(),
            'price' => $price,
            'cost_price' => $cost,
            'stock' => fake()->numberBetween(5, 100),
            'stock_alert' => 10,
            'image' => null,
            'is_active' => true,
        ];
    }
}
