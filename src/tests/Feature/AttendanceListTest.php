<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Rest;
use Carbon\Carbon;
use Illuminate\Support\Str;

class AttendanceListTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 自分の勤怠情報がすべて表示されていることを確認するテスト
     *
     * @return void
     */
    public function test_user_can_see_their_own_attendance_list()
    {
        $user = User::factory()->create();

        $attendance1Date = Carbon::now()->startOfMonth();
        $attendance1 = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => $attendance1Date->toDateString(),
            'work_start' => $attendance1Date->copy()->setHour(9)->setMinute(0)->setSecond(0)->format('H:i:s'),
            'work_end' => $attendance1Date->copy()->setHour(18)->setMinute(0)->setSecond(0)->format('H:i:s'),
            'total_work' => Carbon::createFromTime(8, 0, 0)->format('H:i:s'), // HH:MM:SS形式で統一
        ]);
        $attendance1->rests()->create([
            'rest_start' => $attendance1Date->copy()->setHour(12)->setMinute(0)->setSecond(0)->format('H:i:s'),
            'rest_end' => $attendance1Date->copy()->setHour(13)->setMinute(0)->setSecond(0)->format('H:i:s'),
        ]);

        $attendance2Date = Carbon::now()->startOfMonth()->addDay();
        $attendance2 = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => $attendance2Date->toDateString(),
            'work_start' => $attendance2Date->copy()->setHour(9)->setMinute(5)->setSecond(0)->format('H:i:s'),
            'work_end' => null,
            'total_work' => null,
        ]);

        $attendance3Date = Carbon::now()->startOfMonth()->addDays(2);
        $attendance3 = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => $attendance3Date->toDateString(),
            'work_start' => $attendance3Date->copy()->setHour(10)->setMinute(0)->setSecond(0)->format('H:i:s'),
            'work_end' => $attendance3Date->copy()->setHour(17)->setMinute(30)->setSecond(0)->format('H:i:s'),
            'total_work' => Carbon::createFromTime(7, 0, 0)->format('H:i:s'),
        ]);
        $attendance3->rests()->create([
            'rest_start' => $attendance3Date->copy()->setHour(13)->setMinute(0)->setSecond(0)->format('H:i:s'),
            'rest_end' => $attendance3Date->copy()->setHour(13)->setMinute(30)->setSecond(0)->format('H:i:s'),
        ]);

        $otherUser = User::factory()->create();
        $otherUserAttendanceDate = Carbon::now()->startOfMonth();
        $otherUserAttendance = Attendance::factory()->create([
            'user_id' => $otherUser->id,
            'date' => $otherUserAttendanceDate->toDateString(),
            'work_start' => $otherUserAttendanceDate->copy()->setHour(8)->setMinute(50)->setSecond(0)->format('H:i:s'),
        ]);

        $this->actingAs($user);

        $currentYearMonth = Carbon::now()->format('Y-m');
        $response = $this->get(route('attendance.list', ['yearMonth' => $currentYearMonth]));

        $response->assertStatus(200);
        $response->assertViewIs('attendance_list');
        $response->assertViewHas('attendances');

        $response->assertSee(Carbon::now()->format('Y/m'));


        $response->assertSee($attendance1->date->format('m/d'));
        $response->assertSee($attendance1->date->isoFormat('ddd'));
        $response->assertSee(Carbon::parse($attendance1->work_start)->format('H:i'));
        $response->assertSee(Carbon::parse($attendance1->work_end)->format('H:i'));
        $response->assertSee('01:00');
        $response->assertSee(Carbon::parse($attendance1->total_work)->format('H:i'));
        $response->assertSee(route('attendance.detail', ['id' => $attendance1->id]));

        $response->assertSee($attendance2->date->format('m/d'));
        $response->assertSee($attendance2->date->isoFormat('ddd'));
        $response->assertSee(Carbon::parse($attendance2->work_start)->format('H:i'));
        $response->assertSeeInOrder(['<td class="work-end-value">-</td>'], false);
        $response->assertSeeInOrder(['<td class="rest-value">00:00</td>'], false);
        $response->assertSeeInOrder(['<td class="total-value">-</td>'], false);
        $response->assertSee(route('attendance.detail', ['id' => $attendance2->id]));

        $response->assertSee($attendance3->date->format('m/d'));
        $response->assertSee($attendance3->date->isoFormat('ddd'));
        $response->assertSee(Carbon::parse($attendance3->work_start)->format('H:i'));
        $response->assertSee(Carbon::parse($attendance3->work_end)->format('H:i'));
        $response->assertSee('00:30');
        $response->assertSee(Carbon::parse($attendance3->total_work)->format('H:i'));
        $response->assertSee(route('attendance.detail', ['id' => $attendance3->id]));

        $response->assertDontSee(Carbon::parse($otherUserAttendance->work_start)->format('H:i'));
    }

    /**
     * 勤怠一覧画面に遷移した際に現在の月が表示されることを確認するテスト
     *
     * @return void
     */
    public function test_displays_current_month_when_navigating_to_attendance_list()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $currentYearMonthParam = Carbon::now()->format('Y-m');
        $response = $this->get(route('attendance.list', ['yearMonth' => $currentYearMonthParam]));

        $response->assertStatus(200);
        $response->assertViewIs('attendance_list');

        $expectedDisplayMonth = Carbon::now()->format('Y/m');

        $response->assertSee('&nbsp;' . $expectedDisplayMonth, false);
    }

    /**
     * 「前月」ボタンを押下した時に表示月の前月の情報が表示されるテスト
     *
     * @return void
     */
    public function test_displays_previous_month_info_when_previous_month_button_is_clicked()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $currentDisplayDateForTest = Carbon::create(2025, 5, 15);

        $yearMonthCurrent = $currentDisplayDateForTest->format('Y-m');
        $displayFormatCurrent = $currentDisplayDateForTest->format('Y/m');

        $previousMonthDateForTest = $currentDisplayDateForTest->copy()->subMonth();
        $yearMonthPrevious = $previousMonthDateForTest->format('Y-m');
        $displayFormatPrevious = $previousMonthDateForTest->format('Y/m');

        $attendanceCurrentMonth = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => $currentDisplayDateForTest->copy()->setDateFrom($currentDisplayDateForTest)->startOfMonth()->addDays(4)->toDateString(),
            'work_start' => '09:00:00',
            'work_end' => '18:00:00',
            'total_work' => '08:00:00',
        ]);
        $attendanceCurrentMonth->rests()->create([
            'rest_start' => '12:00:00',
            'rest_end' => '13:00:00',
        ]);


        $attendancePreviousMonth = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => $previousMonthDateForTest->copy()->setDateFrom($previousMonthDateForTest)->startOfMonth()->addDays(2)->toDateString(),
            'work_start' => '10:30:00',
            'work_end' => '19:30:00',
            'total_work' => '08:00:00',
        ]);
        $attendancePreviousMonth->rests()->create([
            'rest_start' => '13:00:00',
            'rest_end' => '14:00:00',
        ]);

        $response = $this->get(route('attendance.list', ['yearMonth' => $yearMonthCurrent]));
        $response->assertStatus(200);
        $response->assertSee('&nbsp;' . $displayFormatCurrent, false);
        $response->assertSee(Carbon::parse($attendanceCurrentMonth->work_start)->format('H:i'));
        $response->assertSee($attendanceCurrentMonth->date->format('m/d'));

        $response = $this->get(route('attendance.list', ['yearMonth' => $yearMonthPrevious]));
        $response->assertStatus(200);

        $response->assertSee('&nbsp;' . $displayFormatPrevious, false);

        $response->assertDontSee('&nbsp;' . $displayFormatCurrent, false);

        $response->assertSee($attendancePreviousMonth->date->format('m/d'));
        $response->assertSee(Carbon::parse($attendancePreviousMonth->work_start)->format('H:i'));
        $response->assertSee(Carbon::parse($attendancePreviousMonth->work_end)->format('H:i'));
        $response->assertSee('01:00', false);
        $response->assertSee(Carbon::parse($attendancePreviousMonth->total_work)->format('H:i'));

        $response->assertDontSee($attendanceCurrentMonth->date->format('m/d'));
        $response->assertDontSee(Carbon::parse($attendanceCurrentMonth->work_start)->format('H:i'));
    }

    /**
     * 「翌月」ボタンを押下した時に表示月の翌月の情報が表示されるテスト
     *
     * @return void
     */
    public function test_displays_next_month_info_when_next_month_button_is_clicked()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $currentDisplayDateForTest = Carbon::create(2025, 5, 15);

        $yearMonthCurrent = $currentDisplayDateForTest->format('Y-m');
        $displayFormatCurrent = $currentDisplayDateForTest->format('Y/m');

        $nextMonthDateForTest = $currentDisplayDateForTest->copy()->addMonth();
        $yearMonthNext = $nextMonthDateForTest->format('Y-m');
        $displayFormatNext = $nextMonthDateForTest->format('Y/m');

        $attendanceCurrentMonth = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => $currentDisplayDateForTest->copy()->startOfMonth()->addDays(7)->toDateString(),
            'work_start' => '08:30:00',
            'work_end' => '17:30:00',
            'total_work' => '08:00:00',
        ]);
        $attendanceCurrentMonth->rests()->create([
            'rest_start' => '12:30:00',
            'rest_end' => '13:30:00',
        ]);

        $attendanceNextMonth = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => $nextMonthDateForTest->copy()->startOfMonth()->addDays(4)->toDateString(),
            'work_start' => '09:45:00',
            'work_end' => '18:45:00',
            'total_work' => '08:00:00',
        ]);
        $attendanceNextMonth->rests()->create([
            'rest_start' => '13:15:00',
            'rest_end' => '14:15:00',
        ]);

        $response = $this->get(route('attendance.list', ['yearMonth' => $yearMonthCurrent]));
        $response->assertStatus(200);
        $response->assertSee('&nbsp;' . $displayFormatCurrent, false);
        $response->assertSee(Carbon::parse($attendanceCurrentMonth->work_start)->format('H:i'));
        $response->assertSee($attendanceCurrentMonth->date->format('m/d'));

        $response = $this->get(route('attendance.list', ['yearMonth' => $yearMonthNext]));
        $response->assertStatus(200);

        $response->assertSee('&nbsp;' . $displayFormatNext, false);

        $response->assertDontSee('&nbsp;' . $displayFormatCurrent, false);

        $response->assertSee($attendanceNextMonth->date->format('m/d'));
        $response->assertSee(Carbon::parse($attendanceNextMonth->work_start)->format('H:i'));
        $response->assertSee(Carbon::parse($attendanceNextMonth->work_end)->format('H:i'));
        $response->assertSee('01:00', false);
        $response->assertSee(Carbon::parse($attendanceNextMonth->total_work)->format('H:i'));

        $response->assertDontSee($attendanceCurrentMonth->date->format('m/d'));
        $response->assertDontSee(Carbon::parse($attendanceCurrentMonth->work_start)->format('H:i'));
    }

    /**
     * 「詳細」ボタンを押下した時にその日の勤怠詳細画面に遷移し、情報が正しく表示されるテスト
     *
     * @return void
     */
    public function test_redirects_to_attendance_detail_page_when_detail_link_is_clicked()
    {
        $user = User::factory()->create(['name' => 'テストユーザー太郎']);
        $attendanceDate = Carbon::create(2025, 5, 10, 0, 0, 0);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => $attendanceDate->toDateString(),
            'work_start' => '09:00:00',
            'work_end' => '18:30:00',
            'total_work' => '08:00:00',
        ]);

        $rest1 = $attendance->rests()->create([
            'rest_start' => '12:00:00',
            'rest_end' => '13:00:00',
        ]);
        $rest2 = $attendance->rests()->create([
            'rest_start' => '15:00:00',
            'rest_end' => '15:30:00',
        ]);

        $this->actingAs($user);

        $yearMonthParam = $attendanceDate->format('Y-m');
        $responseList = $this->get(route('attendance.list', ['yearMonth' => $yearMonthParam]));
        $responseList->assertStatus(200);
        $detailLink = route('attendance.detail', ['id' => $attendance->id]);
        $responseList->assertSee($detailLink);

        $response = $this->get($detailLink);

        $response->assertStatus(200);
        $response->assertViewIs('attendance_detail');

        $response->assertViewHas('attendance', function ($viewAttendance) use ($attendance) {
            return $viewAttendance instanceof Attendance && $viewAttendance->id === $attendance->id;
        });

        $response->assertViewHas('isPendingApproval', false);
        $response->assertViewHas('displayRests', function ($viewRests) use ($attendance) {
            return $viewRests instanceof \Illuminate\Database\Eloquent\Collection &&
                   $viewRests->count() === $attendance->rests->count();
        });


        $response->assertSee('勤怠詳細');
        $response->assertSee($user->name);

        $response->assertSee($attendanceDate->format('Y年'), false);
        $response->assertSee($attendanceDate->format('n月j日'), false);

        $response->assertSee('name="work_start"', false);
        $response->assertSee('value="' . Carbon::parse($attendance->work_start)->format('H:i') . '"', false);
        $response->assertSee('name="work_end"', false);
        $response->assertSee('value="' . Carbon::parse($attendance->work_end)->format('H:i') . '"', false);

        $response->assertSee('name="rest_id[0]" value="' . $rest1->id . '"', false);
        $response->assertSee('name="rest_start[0]"', false);
        $response->assertSee('value="' . Carbon::parse($rest1->rest_start)->format('H:i') . '"', false);
        $response->assertSee('name="rest_end[0]"', false);
        $response->assertSee('value="' . Carbon::parse($rest1->rest_end)->format('H:i') . '"', false);

        $response->assertSee('name="rest_id[1]" value="' . $rest2->id . '"', false);
        $response->assertSee('name="rest_start[1]"', false);
        $response->assertSee('value="' . Carbon::parse($rest2->rest_start)->format('H:i') . '"', false);
        $response->assertSee('name="rest_end[1]"', false);
        $response->assertSee('value="' . Carbon::parse($rest2->rest_end)->format('H:i') . '"', false);

        $nextRestIndex = $attendance->rests->count();
        $response->assertSee('休憩 ' . ($nextRestIndex + 1), false);
        $response->assertSee('name="rest_id[' . $nextRestIndex . ']" value=""', false);
        $response->assertSee('name="rest_start[' . $nextRestIndex . ']" class="rest-start" value=""', false);
        $response->assertSee('name="rest_end[' . $nextRestIndex . ']" class="rest-end" value=""', false);

        $response->assertSee('></textarea>', false);
        $response->assertSee('>' . e($attendance->remarks) . '</textarea>', false);


        $response->assertSee('<button type="submit" class="correction-button">修正</button>', false);
        $response->assertSee('action="' . route('attendance.request_correction', $attendance->id) . '"', false);
    }
}
