<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\User;

class AttendancesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $userIds = User::where('role', 0)->pluck('id')->toArray();

        if (empty($userIds)) {
            $this->command->info('No users with role=0 found.');
            return;
        }

        $startDate = Carbon::create(2025, 3, 1);
        $endDate = Carbon::create(2025, 5, 6);

        $attendances = [];
        $rests = [];
        $attendanceIdCounter = 1;

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            if ($date->isWeekend()) {
                continue;
            }

            foreach ($userIds as $userId) {
                $workStartHour = mt_rand(9, 9);
                $workStartMinute = mt_rand(0, 59);
                $workStartSecond = mt_rand(0, 59);
                $workStartTime = Carbon::instance($date)->setTime($workStartHour, $workStartMinute, $workStartSecond);

                $workEndHour = mt_rand(18, 18);
                $workEndMinute = mt_rand(0, 59);
                $workEndSecond = mt_rand(0, 59);
                $workEndTime = Carbon::instance($date)->setTime($workEndHour, $workEndMinute, $workEndSecond);

                $restStartHour = mt_rand(12, 12);
                $restStartMinute = mt_rand(0, 59);
                $restStartSecond = mt_rand(0, 59);
                $restStartTime = Carbon::instance($date)->setTime($restStartHour, $restStartMinute, $restStartSecond);
                $restEndTime = $restStartTime->copy()->addHour();

                $workDurationSeconds = $workEndTime->diffInSeconds($workStartTime);
                $restDurationSeconds = $restEndTime->diffInSeconds($restStartTime);
                $totalWorkSeconds = $workDurationSeconds - $restDurationSeconds;

                $totalWorkFormatted = gmdate('H:i:s', $totalWorkSeconds);

                $attendanceData = [
                    'id' => $attendanceIdCounter,
                    'user_id' => $userId,
                    'date' => $date->format('Y-m-d'),
                    'work_start' => $workStartTime->format('H:i:s'),
                    'work_end' => $workEndTime->format('H:i:s'),
                    'total_work' => $totalWorkFormatted,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $attendances[] = $attendanceData;

                $restData = [
                    'attendance_id' => $attendanceIdCounter,
                    'rest_start' => $restStartTime->format('H:i:s'),
                    'rest_end' => $restEndTime->format('H:i:s'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $rests[] = $restData;

                $attendanceIdCounter++;
            }
        }

        foreach (array_chunk($attendances, 500) as $chunk) {
            DB::table('attendances')->insert($chunk);
        }

        foreach (array_chunk($rests, 500) as $chunk) {
            DB::table('rests')->insert($chunk);
        }

        $this->command->info('Attendances and Rests tables seeded successfully!');
    }
}
