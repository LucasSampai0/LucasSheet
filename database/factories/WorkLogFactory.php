<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Client;
use App\Models\WorkLog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkLog>
 */
class WorkLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('-20 days', 'now')->format('Y-m-d');
        $startedAt = Carbon::parse($date.' '.fake()->randomElement(['08:00', '09:00', '10:15', '13:00', '14:30', '16:00']));
        $endedAt = (clone $startedAt)->addMinutes(fake()->randomElement([30, 45, 60, 90, 120]));

        return [
            'client_id' => Client::factory(),
            'project_id' => null,
            'category_id' => Category::factory(),
            'work_date' => $date,
            'started_at' => $startedAt->format('H:i'),
            'ended_at' => $endedAt->format('H:i'),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'status' => WorkLog::STATUS_FINISHED,
        ];
    }
}
