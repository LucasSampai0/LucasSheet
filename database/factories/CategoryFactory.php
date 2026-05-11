<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Desenvolvimento', 'Reuniao', 'Suporte', 'Planejamento', 'Revisao']),
            'color' => fake()->hexColor(),
            'active' => fake()->boolean(95),
        ];
    }
}
