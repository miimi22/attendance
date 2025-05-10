<?php

namespace Database\Factories;

use App\Models\Rest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class RestFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Rest::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $restStartTime = $this->faker->time('H:i:s');
        $restEndTime = null;
        if ($this->faker->boolean(90)) {
            $restEndTime = Carbon::parse($restStartTime)
                                ->addMinutes($this->faker->numberBetween(15, 120))
                                ->format('H:i:s');
        }

        return [
            'attendance_id' => \App\Models\Attendance::factory(),
            'rest_start' => $restStartTime,
            'rest_end' => $restEndTime,
        ];
    }
}
