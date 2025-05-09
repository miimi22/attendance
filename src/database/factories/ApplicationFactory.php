<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class ApplicationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Application::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $attendance = Attendance::factory()->create();

        return [
            'attendance_id' => $attendance->id,
            'date' => $attendance->date,
            'remarks' => $this->faker->sentence(10),
            'corrected_work_start' => $this->faker->optional()->time('H:i:s'),
            'corrected_work_end' => $this->faker->optional()->time('H:i:s'),
            'corrected_rests' => function () {
                if ($this->faker->boolean(70)) {
                    return json_encode([[
                        'start' => $this->faker->time('H:i'),
                        'end' => $this->faker->time('H:i')
                    ]]);
                }
                return null;
            },
            'accepted' => $this->faker->randomElement([0, 1, null]), // 0:承認待ち, 1:承認済み, null:未処理など
            'created_at' => $this->faker->dateTimeThisMonth(),
            'updated_at' => $this->faker->dateTimeThisMonth(),
        ];
    }
}
