<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class RestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'attendance_id' => \App\Models\Attendance::factory(),
            'rest_start' => $this->faker->dateTimeThisMonth(),
            'rest_end' => null,
        ];
    }
}
