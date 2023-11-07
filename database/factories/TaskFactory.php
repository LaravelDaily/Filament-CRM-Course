<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'description' => $this->faker->text(),
            'due_date' => $this->faker->dateTimeBetween(Carbon::now()->subDays(7), Carbon::now()->addDays(7)),
            'is_completed' => $this->faker->boolean(),
            'customer_id' => Customer::factory(),
            'user_id' => User::factory(),
        ];
    }
}
