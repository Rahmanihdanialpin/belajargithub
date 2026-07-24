<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Roles/permissions for super admin + admin CRUD toggles
        $this->call(AdminRolesAndPermissionsSeeder::class);

        // Test users for login (customer + admin)
        $this->call(TestUsersSeeder::class);

        $categories = [


            ['name' => 'Cake', 'slug' => 'cake', 'description' => 'Kue lezat dengan berbagai rasa'],
            ['name' => 'Cookies', 'slug' => 'cookies', 'description' => 'Kue kering renyah'],
            ['name' => 'Pudding', 'slug' => 'pudding', 'description' => 'Puding lembut dan silky'],
            ['name' => 'Brownies', 'slug' => 'brownies', 'description' => 'Brownies legit dan fudgy'],
            ['name' => 'Cupcake', 'slug' => 'cupcake', 'description' => 'Cupcake mini yang cantik'],
            ['name' => 'Macaron', 'slug' => 'macaron', 'description' => 'Macaron Perancis autentik'],
        ];

        foreach ($categories as $cat) {
            Category::firstOrCreate(['slug' => $cat['slug']], $cat);
        }

        Product::factory(20)->create();

        $this->call(IngredientSeeder::class);
        $this->call([ProductIngredientSeeder::class,]);
    }
}
