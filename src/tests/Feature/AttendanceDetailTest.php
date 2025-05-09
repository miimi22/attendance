<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;
use Carbon\Carbon;

class AttendanceDetailTest extends TestCase
{
    use RefreshDatabase; // 各テスト実行前にデータベースをリフレッシュ

    /**
     * 勤怠詳細画面の名前欄に、ログインユーザーの氏名が表示されることを確認するテスト
     *
     * @return void
     */
    public function test_displays_correct_users_name_in_attendance_detail()
    {
        $expectedUserName = 'テスト 花子';
        $user = User::factory()->create(['name' => $expectedUserName]);

        $attendanceDate = Carbon::create(2025, 6, 10);
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => $attendanceDate->toDateString(),
            'work_start' => '10:00:00',
            'work_end' => '19:00:00',
            'total_work' => '08:00:00',
        ]);
        $attendance->rests()->create([
            'rest_start' => '13:00:00',
            'rest_end' => '14:00:00',
        ]);

        $this->actingAs($user);

        $response = $this->get(route('attendance.detail', ['id' => $attendance->id]));

        $response->assertStatus(200);
        $response->assertViewIs('attendance_detail');
        $response->assertViewHas('attendance', function ($viewAttendance) use ($attendance) {
            return $viewAttendance instanceof Attendance && $viewAttendance->id === $attendance->id;
        });

        $response->assertSee($expectedUserName);

        $expectedHtmlFragment = '<td class="name-value">' . e($expectedUserName) . '</td>';
        $response->assertSee($expectedHtmlFragment, false);
    }

    /**
     * 勤怠詳細画面の日付欄に、選択した日付が表示されることを確認するテスト
     *
     * @return void
     */
    public function test_displays_correct_date_in_attendance_detail()
    {
        $user = User::factory()->create();

        $testDate = Carbon::create(2025, 7, 20);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => $testDate->toDateString(),
            'work_start' => '09:15:00',
            'work_end' => '18:45:00',
            'total_work' => '08:00:00',
        ]);
        $attendance->rests()->create([
            'rest_start' => '12:15:00',
            'rest_end' => '13:15:00',
        ]);

        $this->actingAs($user);

        $response = $this->get(route('attendance.detail', ['id' => $attendance->id]));

        $response->assertStatus(200);
        $response->assertViewIs('attendance_detail');
        $response->assertViewHas('attendance', function ($viewAttendance) use ($attendance) {
            return $viewAttendance instanceof Attendance && $viewAttendance->id === $attendance->id;
        });

        $expectedYearDisplay = $testDate->format('Y年');
        $expectedMonthDayDisplay = $testDate->format('n月j日');

        $expectedHtmlYear = '<div class="date-year">' . $expectedYearDisplay . '</div>';
        $response->assertSee($expectedHtmlYear, false);

        $expectedHtmlMonthDay = '<div class="date-value">' . $expectedMonthDayDisplay . '</div>';
        $response->assertSee($expectedHtmlMonthDay, false);
    }

    /**
     * 勤怠詳細画面の出勤・退勤欄に、ログインユーザーの打刻が表示されることを確認するテスト
     *
     * @return void
     */
    public function test_displays_correct_work_start_and_end_times_in_attendance_detail()
    {
        $user = User::factory()->create();

        $workStartTimeString = '09:30:00';
        $workEndTimeString = '18:45:00';

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => Carbon::create(2025, 8, 15)->toDateString(),
            'work_start' => $workStartTimeString,
            'work_end' => $workEndTimeString,
            'total_work' => '08:00:00',
        ]);
        $attendance->rests()->create([
            'rest_start' => '12:30:00',
            'rest_end' => '13:30:00',
        ]);

        $this->actingAs($user);

        $response = $this->get(route('attendance.detail', ['id' => $attendance->id]));

        $response->assertStatus(200);
        $response->assertViewIs('attendance_detail');
        $response->assertViewHas('attendance');
        $response->assertViewHas('isPendingApproval', false);

        $expectedWorkStartDisplay = Carbon::parse($workStartTimeString)->format('H:i');
        $expectedWorkEndDisplay = Carbon::parse($workEndTimeString)->format('H:i');

        $response->assertSee('name="work_start"', false);
        $response->assertSee('class="work-start"', false);
        $response->assertSee('value="' . $expectedWorkStartDisplay . '"', false);


        $response->assertSee('name="work_end"', false);
        $response->assertSee('class="work-end"', false);
        $response->assertSee('value="' . $expectedWorkEndDisplay . '"', false);
    }

    /**
     * 勤怠詳細画面の休憩欄に、ログインユーザーの打刻が表示されることを確認するテスト
     *
     * @return void
     */
    public function test_displays_correct_rest_times_and_new_rest_input_in_attendance_detail()
    {
        $user = User::factory()->create();

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => Carbon::create(2025, 9, 20)->toDateString(),
            'work_start' => '09:00:00',
            'work_end' => '19:30:00',
            'total_work' => '08:00:00',
        ]);

        $rest1StartTimeString = '12:00:00';
        $rest1EndTimeString = '13:00:00';
        $rest1 = $attendance->rests()->create([
            'rest_start' => $rest1StartTimeString,
            'rest_end' => $rest1EndTimeString,
        ]);

        $rest2StartTimeString = '15:30:00';
        $rest2EndTimeString = '16:00:00';
        $rest2 = $attendance->rests()->create([
            'rest_start' => $rest2StartTimeString,
            'rest_end' => $rest2EndTimeString,
        ]);

        $this->actingAs($user);

        $response = $this->get(route('attendance.detail', ['id' => $attendance->id]));

        $response->assertStatus(200);
        $response->assertViewIs('attendance_detail');
        $response->assertViewHas('attendance');
        $response->assertViewHas('isPendingApproval', false);
        $response->assertViewHas('displayRests', function ($viewRests) use ($attendance) {
            return $viewRests instanceof \Illuminate\Database\Eloquent\Collection &&
                   $viewRests->count() === $attendance->rests->count();
        });

        $expectedRest1StartDisplay = Carbon::parse($rest1StartTimeString)->format('H:i');
        $expectedRest1EndDisplay = Carbon::parse($rest1EndTimeString)->format('H:i');
        $expectedRest2StartDisplay = Carbon::parse($rest2StartTimeString)->format('H:i');
        $expectedRest2EndDisplay = Carbon::parse($rest2EndTimeString)->format('H:i');

        $response->assertSeeInOrder(['<td class="rest">', '休憩', '</td>'], false);

        $patternRestId0 = sprintf(
            '/<input\s+type="hidden"\s+name="rest_id\[0\]"\s+value="%s"\s*>/s',
            preg_quote($rest1->id, '/')
        );
        $this->assertMatchesRegularExpression($patternRestId0, $response->getContent());

        $patternRestStart0 = sprintf(
            '/<input\s+type="time"\s+name="rest_start\[0\]"\s+class="rest-start"\s+value="%s"[^>]*>/s',
            preg_quote($expectedRest1StartDisplay, '/')
        );
        $this->assertMatchesRegularExpression($patternRestStart0, $response->getContent());

        $patternRestEnd0 = sprintf(
            '/<input\s+type="time"\s+name="rest_end\[0\]"\s+class="rest-end"\s+value="%s"[^>]*>/s',
            preg_quote($expectedRest1EndDisplay, '/')
        );
        $this->assertMatchesRegularExpression($patternRestEnd0, $response->getContent());

        $response->assertSeeInOrder(['<td class="rest">', '休憩 2', '</td>'], false);

        $patternRestId1 = sprintf(
            '/<input\s+type="hidden"\s+name="rest_id\[1\]"\s+value="%s"\s*>/s',
            preg_quote($rest2->id, '/')
        );
        $this->assertMatchesRegularExpression($patternRestId1, $response->getContent());

        $patternRestStart1 = sprintf(
            '/<input\s+type="time"\s+name="rest_start\[1\]"\s+class="rest-start"\s+value="%s"[^>]*>/s',
            preg_quote($expectedRest2StartDisplay, '/')
        );
        $this->assertMatchesRegularExpression($patternRestStart1, $response->getContent());

        $patternRestEnd1 = sprintf(
            '/<input\s+type="time"\s+name="rest_end\[1\]"\s+class="rest-end"\s+value="%s"[^>]*>/s',
            preg_quote($expectedRest2EndDisplay, '/')
        );
        $this->assertMatchesRegularExpression($patternRestEnd1, $response->getContent());

        $nextRestIndex = 2;
        $response->assertSeeInOrder(['<td class="rest">', '休憩 ' . ($nextRestIndex + 1), '</td>'], false);

        $patternNextRestId = sprintf(
            '/<input\s+type="hidden"\s+name="rest_id\[%s\]"\s+value=""\s*>/s',
            $nextRestIndex
        );
        $this->assertMatchesRegularExpression($patternNextRestId, $response->getContent());

        $patternNextRestStart = sprintf(
            '/<input\s+type="time"\s+name="rest_start\[%s\]"\s+class="rest-start"\s+value=""[^>]*>/s',
            $nextRestIndex
        );
        $this->assertMatchesRegularExpression($patternNextRestStart, $response->getContent());

        $patternNextRestEnd = sprintf(
            '/<input\s+type="time"\s+name="rest_end\[%s\]"\s+class="rest-end"\s+value=""[^>]*>/s',
            $nextRestIndex
        );
        $this->assertMatchesRegularExpression($patternNextRestEnd, $response->getContent());
    }
}
