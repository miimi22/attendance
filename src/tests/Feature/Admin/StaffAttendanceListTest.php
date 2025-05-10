<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Rest;
use Carbon\Carbon;

class StaffAttendanceListTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 管理者のスタッフ一覧画面で全一般ユーザーの情報が正しく表示されることを確認するテスト
     *
     * @return void
     */
    public function test_admin_can_view_all_general_users_on_staff_list_page()
    {
        $adminUser = User::factory()->admin()->create();
        $this->actingAs($adminUser);

        $generalUser1 = User::factory()->create([
            'name' => '一般 太郎',
            'email' => 'taro.ippan@example.com',
            'role' => 0,
        ]);
        $generalUser2 = User::factory()->create([
            'name' => '一般 花子',
            'email' => 'hanako.ippan@example.com',
            'role' => 0,
        ]);

        $anotherAdmin = User::factory()->admin()->create([
            'name' => '別管 理者',
            'email' => 'another.admin@example.com',
        ]);


        $response = $this->get(route('admin.staff.list'));


        $response->assertStatus(200);
        $response->assertViewIs('admin.staff_list');

        $response->assertSee('<title>スタッフ一覧画面（管理者）</title>', false);
        $response->assertSee('<h1 class="staff-list-title">スタッフ一覧</h1>', false);

        $response->assertSee('<td class="name">名前</td>', false);
        $response->assertSee('<td class="email">メールアドレス</td>', false);
        $response->assertSee('<td class="month-attendance">月次勤怠</td>', false);

        $response->assertSee($generalUser1->name);
        $response->assertSee($generalUser1->email);
        $response->assertSee(route('admin.staff.attendance.list', ['id' => $generalUser1->id]));

        $response->assertSee($generalUser2->name);
        $response->assertSee($generalUser2->email);
        $response->assertSee(route('admin.staff.attendance.list', ['id' => $generalUser2->id]));

        $response->assertDontSee($anotherAdmin->name);
        $response->assertDontSee($anotherAdmin->email);

        $response->assertDontSee($adminUser->name);
        $response->assertDontSee($adminUser->email);
    }

    /**
     * 管理者のスタッフ別勤怠一覧画面で選択したスタッフの勤怠情報が正しく表示されることを確認するテスト
     *
     * @return void
     */
    public function test_admin_can_view_staff_monthly_attendance_correctly()
    {
        $adminUser = User::factory()->admin()->create();
        $this->actingAs($adminUser);

        $staffUser = User::factory()->create(['name' => '勤怠 太郎']);

        $targetYearMonth = '2025-04';
        $targetDate = Carbon::createFromFormat('Y-m', $targetYearMonth);

        $attendance1 = Attendance::factory()->create([
            'user_id' => $staffUser->id,
            'date' => $targetDate->copy()->day(1)->format('Y-m-d'),
            'work_start' => '09:00:00',
            'work_end' => '18:00:00',
            'total_work' => '08:00:00'
        ]);
        Rest::factory()->create([
            'attendance_id' => $attendance1->id,
            'rest_start' => '12:00:00',
            'rest_end' => '13:00:00',
        ]);

        $attendance2 = Attendance::factory()->create([
            'user_id' => $staffUser->id,
            'date' => $targetDate->copy()->day(2)->format('Y-m-d'),
            'work_start' => '10:00:00',
            'work_end' => '17:30:00',
            'total_work' => '07:30:00'
        ]);

        Attendance::factory()->create([
            'user_id' => $staffUser->id,
            'date' => $targetDate->copy()->subMonth()->day(15)->format('Y-m-d'),
            'work_start' => '09:00:00',
            'work_end' => '17:00:00',
            'total_work' => '08:00:00'
        ]);

        $response = $this->get(route('admin.staff.attendance.list', ['id' => $staffUser->id, 'yearMonth' => $targetYearMonth]));

        $response->assertStatus(200);
        $response->assertViewIs('admin.staff_attendance_list');

        $response->assertSee($staffUser->name . 'さんの勤怠');
        $response->assertSee($targetDate->format('Y/m'));

        $response->assertSeeInOrder(['日付', '出勤', '退勤', '休憩', '合計', '詳細']);

        $response->assertSee($attendance1->date->format('m/d') . '(' . $attendance1->date->isoFormat('ddd') . ')');
        $response->assertSee(Carbon::parse($attendance1->work_start)->format('H:i'));
        $response->assertSee(Carbon::parse($attendance1->work_end)->format('H:i'));
        $response->assertSeeInOrder([
            $attendance1->date->format('m/d') . '(' . $attendance1->date->isoFormat('ddd') . ')',
            Carbon::parse($attendance1->work_start)->format('H:i'),
            Carbon::parse($attendance1->work_end)->format('H:i'),
            '01:00',
            '08:00',
            route('attendance.detail', ['id' => $attendance1->id])
        ]);


        $response->assertSee($attendance2->date->format('m/d') . '(' . $attendance2->date->isoFormat('ddd') . ')');
        $response->assertSee(Carbon::parse($attendance2->work_start)->format('H:i'));
        $response->assertSee(Carbon::parse($attendance2->work_end)->format('H:i'));
        $response->assertSeeInOrder([
            $attendance2->date->format('m/d') . '(' . $attendance2->date->isoFormat('ddd') . ')',
            Carbon::parse($attendance2->work_start)->format('H:i'),
            Carbon::parse($attendance2->work_end)->format('H:i'),
            '0:00',
            '07:30',
            route('attendance.detail', ['id' => $attendance2->id])
        ]);

        $response->assertSee(route('admin.staff.attendance.export', ['id' => $staffUser->id, 'yearMonth' => $targetYearMonth]));
        $response->assertSee('CSV出力');

        $prevMonth = $targetDate->copy()->subMonth()->format('Y-m');
        $nextMonth = $targetDate->copy()->addMonth()->format('Y-m');
        $response->assertSee(route('admin.staff.attendance.list', ['id' => $staffUser->id, 'yearMonth' => $prevMonth]));
        $response->assertSee(route('admin.staff.attendance.list', ['id' => $staffUser->id, 'yearMonth' => $nextMonth]));
    }

    /**
     * スタッフ別勤怠一覧画面で「前月」ボタンを押下すると前月の勤怠が表示されることを確認するテスト
     *
     * @return void
     */
    public function test_admin_can_navigate_to_previous_month_on_staff_attendance_list()
    {
        $adminUser = User::factory()->admin()->create();
        $this->actingAs($adminUser);

        $staffUser = User::factory()->create(['name' => '月ナビ 太郎']);

        $currentDisplayMonth = Carbon::create(2025, 4, 1);
        $previousMonth = $currentDisplayMonth->copy()->subMonth();
        $furtherPreviousMonth = $previousMonth->copy()->subMonth();

        $attendanceApril = Attendance::factory()->create([
            'user_id' => $staffUser->id,
            'date' => $currentDisplayMonth->copy()->day(5)->format('Y-m-d'),
            'work_start' => '09:00:00',
            'work_end' => '18:00:00',
            'total_work' => '08:00:00',
        ]);

        $attendanceMarch = Attendance::factory()->create([
            'user_id' => $staffUser->id,
            'date' => $previousMonth->copy()->day(10)->format('Y-m-d'),
            'work_start' => '10:00:00',
            'work_end' => '17:00:00',
            'total_work' => '07:00:00',
        ]);

        $initialResponse = $this->get(route('admin.staff.attendance.list', [
            'id' => $staffUser->id,
            'yearMonth' => $currentDisplayMonth->format('Y-m')
        ]));
        $initialResponse->assertStatus(200);
        $initialResponse->assertSee($currentDisplayMonth->format('Y/m'));
        $initialResponse->assertSee($attendanceApril->date->format('m/d'));

        $previousMonthUrl = route('admin.staff.attendance.list', [
            'id' => $staffUser->id,
            'yearMonth' => $previousMonth->format('Y-m')
        ]);
        $responseAfterClickingPrevious = $this->get($previousMonthUrl);

        $responseAfterClickingPrevious->assertStatus(200);

        $responseAfterClickingPrevious->assertSee($staffUser->name . 'さんの勤怠');
        $responseAfterClickingPrevious->assertSee($previousMonth->format('Y/m'));

        $responseAfterClickingPrevious->assertSee($attendanceMarch->date->format('m/d') . '(' . $attendanceMarch->date->isoFormat('ddd') . ')');
        $responseAfterClickingPrevious->assertSee(Carbon::parse($attendanceMarch->work_start)->format('H:i'));
        $responseAfterClickingPrevious->assertSee(Carbon::parse($attendanceMarch->work_end)->format('H:i'));
        $responseAfterClickingPrevious->assertSee('07:00');

        $responseAfterClickingPrevious->assertDontSee($attendanceApril->date->format('m/d'));

        $responseAfterClickingPrevious->assertSee(route('admin.staff.attendance.list', [
            'id' => $staffUser->id,
            'yearMonth' => $furtherPreviousMonth->format('Y-m')
        ]));

        $responseAfterClickingPrevious->assertSee(route('admin.staff.attendance.list', [
            'id' => $staffUser->id,
            'yearMonth' => $currentDisplayMonth->format('Y-m')
        ]));
    }

    /**
     * スタッフ別勤怠一覧画面で「翌月」ボタンを押下すると翌月の勤怠が表示されることを確認するテスト
     *
     * @return void
     */
    public function test_admin_can_navigate_to_next_month_on_staff_attendance_list()
    {
        $adminUser = User::factory()->admin()->create();
        $this->actingAs($adminUser);

        $staffUser = User::factory()->create(['name' => '月ナビ 次郎']);

        $currentDisplayMonth = Carbon::create(2025, 4, 1);
        $nextMonth = $currentDisplayMonth->copy()->addMonth();
        $furtherNextMonth = $nextMonth->copy()->addMonth();

        $attendanceApril = Attendance::factory()->create([
            'user_id' => $staffUser->id,
            'date' => $currentDisplayMonth->copy()->day(15)->format('Y-m-d'),
            'work_start' => '09:30:00',
            'work_end' => '18:30:00',
            'total_work' => '08:00:00',
        ]);
        Rest::factory()->create([
            'attendance_id' => $attendanceApril->id,
            'rest_start' => '12:30:00',
            'rest_end' => '13:30:00',
        ]);


        $attendanceMay = Attendance::factory()->create([
            'user_id' => $staffUser->id,
            'date' => $nextMonth->copy()->day(20)->format('Y-m-d'),
            'work_start' => '10:30:00',
            'work_end' => '17:30:00',
            'total_work' => '07:00:00',
        ]);

        $initialResponse = $this->get(route('admin.staff.attendance.list', [
            'id' => $staffUser->id,
            'yearMonth' => $currentDisplayMonth->format('Y-m')
        ]));
        $initialResponse->assertStatus(200);
        $initialResponse->assertSee($currentDisplayMonth->format('Y/m'));
        $initialResponse->assertSee($attendanceApril->date->format('m/d'));

        $nextMonthUrl = route('admin.staff.attendance.list', [
            'id' => $staffUser->id,
            'yearMonth' => $nextMonth->format('Y-m')
        ]);
        $responseAfterClickingNext = $this->get($nextMonthUrl);

        $responseAfterClickingNext->assertStatus(200);

        $responseAfterClickingNext->assertSee($staffUser->name . 'さんの勤怠');
        $responseAfterClickingNext->assertSee($nextMonth->format('Y/m'));

        $responseAfterClickingNext->assertSee($attendanceMay->date->format('m/d') . '(' . $attendanceMay->date->isoFormat('ddd') . ')');
        $responseAfterClickingNext->assertSee(Carbon::parse($attendanceMay->work_start)->format('H:i'));
        $responseAfterClickingNext->assertSee(Carbon::parse($attendanceMay->work_end)->format('H:i'));
        $responseAfterClickingNext->assertSee('07:00');

        $responseAfterClickingNext->assertDontSee($attendanceApril->date->format('m/d'));

        $responseAfterClickingNext->assertSee(route('admin.staff.attendance.list', [
            'id' => $staffUser->id,
            'yearMonth' => $currentDisplayMonth->format('Y-m')
        ]));

        $responseAfterClickingNext->assertSee(route('admin.staff.attendance.list', [
            'id' => $staffUser->id,
            'yearMonth' => $furtherNextMonth->format('Y-m')
        ]));
    }

    /**
     * スタッフ別勤怠一覧画面の「詳細」ボタンを押下すると勤怠詳細画面に遷移することを確認するテスト
     *
     * @return void
     */
    public function test_admin_can_navigate_to_attendance_detail_from_staff_attendance_list()
    {
        $adminUser = User::factory()->admin()->create();
        $this->actingAs($adminUser);

        $staffUser = User::factory()->create(['name' => '詳細確認 太郎']);
        $targetYearMonth = '2025-07';
        $targetDate = Carbon::createFromFormat('Y-m', $targetYearMonth)->day(10);

        $attendanceToDetail = Attendance::factory()->create([
            'user_id' => $staffUser->id,
            'date' => $targetDate->format('Y-m-d'),
            'work_start' => '09:00:00',
            'work_end' => '17:00:00',
            'total_work' => '08:00:00',
        ]);

        $staffAttendanceListResponse = $this->get(route('admin.staff.attendance.list', [
            'id' => $staffUser->id,
            'yearMonth' => $targetYearMonth
        ]));
        $staffAttendanceListResponse->assertStatus(200);
        $staffAttendanceListResponse->assertSee($attendanceToDetail->date->format('m/d'));

        $detailUrl = route('attendance.detail', ['id' => $attendanceToDetail->id]);
        $detailPageResponse = $this->get($detailUrl);

        $detailPageResponse->assertStatus(200);
        $detailPageResponse->assertViewIs('admin.attendance_detail');

        $detailPageResponse->assertSee('勤怠詳細</h1>', false);
        $detailPageResponse->assertSee($staffUser->name);
        $detailPageResponse->assertSee($targetDate->format('Y年'));
        $detailPageResponse->assertSee($targetDate->format('n月j日'));
        $detailPageResponse->assertSee('name="work_start" class="work-start" value="' . Carbon::parse($attendanceToDetail->work_start)->format('H:i') . '"', false);
        $detailPageResponse->assertSee('name="work_end" class="work-end" value="' . Carbon::parse($attendanceToDetail->work_end)->format('H:i') . '"', false);
    }
}
