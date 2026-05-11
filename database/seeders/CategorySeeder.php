<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Desenvolvimento', 'color' => '#EB088D'],
            ['name' => 'Reuniao', 'color' => '#6366f1'],
            ['name' => 'Suporte', 'color' => '#10b981'],
            ['name' => 'Planejamento', 'color' => '#f59e0b'],
            ['name' => 'Revisao', 'color' => '#0ea5e9'],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(['name' => $category['name']], $category + ['active' => true]);
        }
    }
}
