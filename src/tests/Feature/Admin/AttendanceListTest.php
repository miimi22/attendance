<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Rest;
use Carbon\Carbon;

class AttendanceListTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2025, 5, 10, 0, 0, 0));
    }

    public function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * 管理者は勤怠一覧画面でその日の全ユーザーの勤怠情報を正確に確認できることを確認するテスト
     *
     * @return void
     */
    public function test_admin_can_view_daily_attendance_list_accurately()
    {
        $adminUser = User::factory()->admin()->create();
        $this->actingAs($adminUser);

        $today = Carbon::today();
        $todayString = $today->format('Y-m-d');

        $user1 = User::factory()->create(['name' => '山田 太郎']);
        $attendance1 = Attendance::factory()->create([
            'user_id' => $user1->id,
            'date' => $todayString,
            'work_start' => '09:00:00',
            'work_end' => '18:00:00',
        ]);
        Rest::factory()->create([
            'attendance_id' => $attendance1->id,
            'rest_start' => '12:00:00',
            'rest_end' => '13:00:00',
        ]);

        $user2 = User::factory()->create(['name' => '鈴木 一郎']);
        $attendance2 = Attendance::factory()->create([
            'user_id' => $user2->id,
            'date' => $todayString,
            'work_start' => '10:00:00',
            'work_end' => '17:30:00',
        ]);
        Rest::factory()->create([
            'attendance_id' => $attendance2->id,
            'rest_start' => '12:30:00',
            'rest_end' => '13:00:00',
        ]);
        Rest::factory()->create([
            'attendance_id' => $attendance2->id,
            'rest_start' => '15:00:00',
            'rest_end' => '15:30:00',
        ]);

        $user3 = User::factory()->create(['name' => '佐藤 花子']);

        $yesterday = Carbon::yesterday()->format('Y-m-d');
        Attendance::factory()->create([
            'user_id' => $user1->id,
            'date' => $yesterday,
            'work_start' => '09:00:00',
            'work_end' => '17:00:00',
        ]);


        $response = $this->get(route('admin.attendance.list', ['date' => $todayString]));


        $response->assertStatus(200);

        $response->assertSee($today->format('Y/m/d'));
        $response->assertSee($today->format('Y年m月d日') . 'の勤怠');

        $response->assertSee($user1->name);
        $response->assertSee('09:00');
        $response->assertSee('18:00');
        $response->assertSee('01:00');
        $response->assertSee('08:00');
        $response->assertSee(route('attendance.detail', ['id' => $attendance1->id]));

        $response->assertSee($user2->name);
        $response->assertSee('10:00');
        $response->assertSee('17:30');
        $response->assertSee('01:00');
        $response->assertSee('06:30');
        $response->assertSee(route('attendance.detail', ['id' => $attendance2->id]));

        $response->assertDontSee(e($user3->name));


        $previousDate = $today->copy()->subDay()->format('Y-m-d');
        $nextDate = $today->copy()->addDay()->format('Y-m-d');
        $response->assertSee(route('admin.attendance.list', ['date' => $previousDate]));
        $response->assertSee(route('admin.attendance.list', ['date' => $nextDate]));
    }

    /**
     * 管理者の勤怠一覧画面に遷移した際に、現在の日付が表示されることを確認するテスト
     *
     * @return void
     */
    public function test_admin_attendance_list_shows_current_date_by_default_when_no_date_is_specified()
    {
        $adminUser = User::factory()->admin()->create();
        $this->actingAs($adminUser);

        $today = Carbon::today();


        $response = $this->get(route('admin.attendance.list'));


        $response->assertStatus(200);

        $response->assertSee($today->format('Y年m月d日') . 'の勤怠');

        $response->assertSee($today->format('Y/m/d'));

        $expectedPreviousDate = $today->copy()->subDay()->format('Y-m-d');
        $expectedNextDate = $today->copy()->addDay()->format('Y-m-d');


        $response->assertSee('href="' . url('/admin/attendance/list/' . $expectedPreviousDate) . '"', false);
        $response->assertSee('href="' . url('/admin/attendance/list/' . $expectedNextDate) . '"', false);
    }

    /**
     * 管理者の勤怠一覧画面で「前日」ボタンを押下すると前日の勤怠が表示されることを確認するテスト
     *
     * @return void
     */
    public function test_admin_can_navigate_to_previous_day_attendance_list_by_clicking_previous_day_button()
    {
        $adminUser = User::factory()->admin()->create();
        $this->actingAs($adminUser);

        $today = Carbon::today();
        $yesterday = $today->copy()->subDay();
        $dayBeforeYesterday = $yesterday->copy()->subDay();

        $userYesterday = User::factory()->create(['name' => '昨日 テストユーザー']);
        $attendanceYesterday = Attendance::factory()->create([
            'user_id' => $userYesterday->id,
            'date' => $yesterday->format('Y-m-d'),
            'work_start' => '09:30:00',
            'work_end' => '18:30:00',
        ]);
        Rest::factory()->create([
            'attendance_id' => $attendanceYesterday->id,
            'rest_start' => '12:30:00',
            'rest_end' => '13:30:00',
        ]);

        $userToday = User::factory()->create(['name' => '今日 テストユーザー']);
        Attendance::factory()->create([
            'user_id' => $userToday->id,
            'date' => $today->format('Y-m-d'),
            'work_start' => '10:00:00',
            'work_end' => '19:00:00',
        ]);



        $initialResponse = $this->get(route('admin.attendance.list'));
        $initialResponse->assertStatus(200);
        $initialResponse->assertSee($today->format('Y年m月d日') . 'の勤怠');
        $initialResponse->assertSee($userToday->name);

        $previousDayUrl = url('/admin/attendance/list/' . $yesterday->format('Y-m-d'));
        $responseAfterClickingPrevious = $this->get($previousDayUrl);


        $responseAfterClickingPrevious->assertStatus(200);

        $responseAfterClickingPrevious->assertSee($yesterday->format('Y年m月d日') . 'の勤怠');
        $responseAfterClickingPrevious->assertSee($yesterday->format('Y/m/d'));

        $responseAfterClickingPrevious->assertSee($userYesterday->name);
        $responseAfterClickingPrevious->assertSee('09:30');
        $responseAfterClickingPrevious->assertSee('18:30');
        $responseAfterClickingPrevious->assertSee('01:00');
        $responseAfterClickingPrevious->assertSee('08:00');

        $responseAfterClickingPrevious->assertDontSee($userToday->name);

        $responseAfterClickingPrevious->assertSee('href="' . url('/admin/attendance/list/' . $dayBeforeYesterday->format('Y-m-d')) . '"', false);

        $responseAfterClickingPrevious->assertSee('href="' . url('/admin/attendance/list/' . $today->format('Y-m-d')) . '"', false);
    }

    /**
     * 管理者の勤怠一覧画面で「翌日」ボタンを押下すると翌日の勤怠が表示されることを確認するテスト
     *
     * @return void
     */
    public function test_admin_can_navigate_to_next_day_attendance_list_by_clicking_next_day_button()
    {
        $adminUser = User::factory()->admin()->create();
        $this->actingAs($adminUser);

        $today = Carbon::today();
        $tomorrow = $today->copy()->addDay();
        $dayAfterTomorrow = $tomorrow->copy()->addDay();

        $userTomorrow = User::factory()->create(['name' => '明日 出勤者']);
        $attendanceTomorrow = Attendance::factory()->create([
            'user_id' => $userTomorrow->id,
            'date' => $tomorrow->format('Y-m-d'),
            'work_start' => '10:15:00',
            'work_end' => '19:45:00',
        ]);
        Rest::factory()->create([
            'attendance_id' => $attendanceTomorrow->id,
            'rest_start' => '13:00:00',
            'rest_end' => '14:15:00',
        ]);

        $userToday = User::factory()->create(['name' => '今日 出勤者 (翌日テスト用)']);
        Attendance::factory()->create([
            'user_id' => $userToday->id,
            'date' => $today->format('Y-m-d'),
            'work_start' => '09:00:00',
            'work_end' => '18:00:00',
        ]);


        $initialResponse = $this->get(route('admin.attendance.list'));
        $initialResponse->assertStatus(200);
        $initialResponse->assertSee($today->format('Y年m月d日') . 'の勤怠');
        $initialResponse->assertSee($userToday->name);

        $nextDayUrl = url('/admin/attendance/list/' . $tomorrow->format('Y-m-d'));
        $responseAfterClickingNext = $this->get($nextDayUrl);


        $responseAfterClickingNext->assertStatus(200);

        $responseAfterClickingNext->assertSee($tomorrow->format('Y年m月d日') . 'の勤怠');
        $responseAfterClickingNext->assertSee($tomorrow->format('Y/m/d'));

        $responseAfterClickingNext->assertSee($userTomorrow->name);
        $responseAfterClickingNext->assertSee('10:15');
        $responseAfterClickingNext->assertSee('19:45');
        $responseAfterClickingNext->assertSee('01:15');
        $responseAfterClickingNext->assertSee('08:15');

        $responseAfterClickingNext->assertDontSee($userToday->name);

        $responseAfterClickingNext->assertSee('href="' . url('/admin/attendance/list/' . $today->format('Y-m-d')) . '"', false);

        $responseAfterClickingNext->assertSee('href="' . url('/admin/attendance/list/' . $dayAfterTomorrow->format('Y-m-d')) . '"', false);
    }
}
