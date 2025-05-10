<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class AttendanceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Attendance::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $workStartTime = $this->faker->time('H:i:s');
        $workEndTime = null;
        if ($this->faker->boolean(80)) {
            $workEndTime = Carbon::parse($workStartTime)->addHours($this->faker->numberBetween(1, 9))->format('H:i:s');
        }

        return [
            'user_id' => User::factory(),
            'date' => Carbon::today()->format('Y-m-d'),
            'work_start' => $workStartTime,
            'work_end' => $workEndTime,
            'total_work' => null,
        ];
    }
}
