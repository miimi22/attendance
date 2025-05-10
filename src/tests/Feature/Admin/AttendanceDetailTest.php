<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Rest;
use Carbon\Carbon;

class AttendanceDetailTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        // テスト実行中の「現在時刻」を固定 (日付の表示テストのため)
        Carbon::setTestNow(Carbon::create(2025, 5, 10, 10, 0, 0));
    }

    public function tearDown(): void
    {
        Carbon::setTestNow(); // 現在時刻の固定を解除
        parent::tearDown();
    }

    /**
     * 管理者の勤怠詳細画面の内容が選択した情報と一致することを確認するテスト
     *
     * @return void
     */
    public function test_admin_can_view_attendance_detail_with_correct_information_including_one_rest()
    {
        $adminUser = User::factory()->admin()->create();
        $this->actingAs($adminUser);

        $targetUser = User::factory()->create(['name' => '詳細 テスト対象者']);
        $targetDate = Carbon::create(2025, 4, 15);

        $attendance = Attendance::factory()->create([
            'user_id' => $targetUser->id,
            'date' => $targetDate->format('Y-m-d'),
            'work_start' => '09:00:00',
            'work_end' => '18:30:00',
        ]);

        $rest1 = Rest::factory()->create([
            'attendance_id' => $attendance->id,
            'rest_start' => '12:00:00',
            'rest_end' => '13:00:00',
        ]);


        $response = $this->get(route('attendance.detail', ['id' => $attendance->id]));


        $response->assertStatus(200);
        $response->assertViewIs('admin.attendance_detail');

        $response->assertSee('<title>勤怠詳細画面（管理者）</title>', false);
        $response->assertSee('勤怠詳細</h1>', false);

        $response->assertSee($targetUser->name);

        $response->assertSee($targetDate->format('Y年'));
        $response->assertSee($targetDate->format('n月j日'));

        $response->assertSee('name="work_start" class="work-start" value="' . Carbon::parse($attendance->work_start)->format('H:i') . '"', false);
        $response->assertSee('name="work_end" class="work-end" value="' . Carbon::parse($attendance->work_end)->format('H:i') . '"', false);


        $response->assertSeeInOrder(['<td class="rest">', '休憩 ', 'name="rest_id[0]"'], false);
        $response->assertSee('name="rest_id[0]" value="' . $rest1->id . '"', false);
        $response->assertSee('name="rest_start[0]" class="rest-start" value="' . Carbon::parse($rest1->rest_start)->format('H:i') . '"', false);
        $response->assertSee('name="rest_end[0]" class="rest-end" value="' . Carbon::parse($rest1->rest_end)->format('H:i') . '"', false);

        $response->assertSeeInOrder(['<td class="rest">', '休憩 2', 'name="rest_id[1]"'], false);
        $response->assertSee('name="rest_id[1]" value=""', false);
        $response->assertSeeInOrder(['name="rest_start[1]" class="rest-start"', 'value=""'], false);
        $response->assertSeeInOrder(['name="rest_end[1]" class="rest-end"', 'value=""'], false);

        $response->assertSee('<textarea name="remarks"', false);
        $response->assertSee('</textarea>', false);

        $response->assertSee('修正</button>', false);

        $response->assertSee('action="' . route('admin.attendance.request_correction', ['id' => $attendance->id]) . '"', false);
    }

    /**
     * 勤怠詳細画面で出勤時間が退勤時間より後の場合、バリデーションエラーが表示されることを確認するテスト
     *
     * @return void
     */
    public function test_validation_error_occurs_when_work_start_is_after_work_end_on_detail_submission()
    {
        $adminUser = User::factory()->admin()->create();
        $this->actingAs($adminUser);

        $targetUser = User::factory()->create();
        $attendance = Attendance::factory()->create([
            'user_id' => $targetUser->id,
            'work_start' => '09:00:00',
            'work_end' => '18:00:00',
        ]);
        $rest1 = Rest::factory()->create([
            'attendance_id' => $attendance->id,
            'rest_start' => '12:00:00',
            'rest_end' => '13:00:00',
        ]);

        $response = $this->post(route('admin.attendance.request_correction', ['id' => $attendance->id]), [
            'work_start' => '19:00:00',
            'work_end' => '18:00:00',
            'remarks' => 'テスト備考：出勤時間エラー',
            'rest_id' => [
                0 => $rest1->id,
                1 => '',
            ],
            'rest_start' => [
                0 => '12:00',
                1 => '',
            ],
            'rest_end' => [
                0 => '13:00',
                1 => '',
            ],
            '_token' => csrf_token(),
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('work_start');
        $response->assertSessionHasErrors([
            'work_start' => '出勤時間もしくは退勤時間が不適切な値です'
        ]);

        $followResponse = $this->get(route('attendance.detail', ['id' => $attendance->id]));
        $followResponse->assertSee('出勤時間もしくは退勤時間が不適切な値です');
    }

    /**
     * 勤怠詳細画面で休憩開始時間が退勤時間より後の場合、バリデーションエラーが表示されることを確認するテスト
     *
     * @return void
     */
    public function test_validation_error_occurs_when_rest_start_is_after_work_end_on_detail_submission()
    {
        $adminUser = User::factory()->admin()->create();
        $this->actingAs($adminUser);

        $targetUser = User::factory()->create();
        $attendance = Attendance::factory()->create([
            'user_id' => $targetUser->id,
            'work_start' => '09:00:00',
            'work_end' => '18:00:00',
        ]);

        $response = $this->post(route('admin.attendance.request_correction', ['id' => $attendance->id]), [
            'work_start' => '09:00:00',
            'work_end' => '18:00:00',
            'remarks' => 'テスト備考：休憩時間エラー',
            'rest_id' => [
                0 => '',
            ],
            'rest_start' => [
                0 => '19:00:00',
            ],
            'rest_end' => [
                0 => '19:30:00',
            ],
            '_token' => csrf_token(),
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('rest_time_range.0');
        $response->assertSessionHasErrors([
            'rest_time_range.0' => '休憩時間が勤務時間外です'
        ]);

        $followResponse = $this->get(route('attendance.detail', ['id' => $attendance->id]));
        $followResponse->assertSee('休憩時間が勤務時間外です');
    }

    /**
     * 勤怠詳細画面で休憩終了時間が退勤時間より後の場合、バリデーションエラーが表示されることを確認するテスト
     *
     * @return void
     */
    public function test_validation_error_occurs_when_rest_end_is_after_work_end_on_detail_submission()
    {
        $adminUser = User::factory()->admin()->create();
        $this->actingAs($adminUser);

        $targetUser = User::factory()->create();
        $attendance = Attendance::factory()->create([
            'user_id' => $targetUser->id,
            'work_start' => '09:00:00',
            'work_end' => '18:00:00',
        ]);

        $response = $this->post(route('admin.attendance.request_correction', ['id' => $attendance->id]), [
            'work_start' => '09:00:00',
            'work_end' => '18:00:00',
            'remarks' => 'テスト備考：休憩時間エラー（終了が退勤後）',
            'rest_id' => [
                0 => '',
            ],
            'rest_start' => [
                0 => '17:00:00',
            ],
            'rest_end' => [
                0 => '18:30:00',
            ],
            '_token' => csrf_token(),
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('rest_time_range.0');
        $response->assertSessionHasErrors([
            'rest_time_range.0' => '休憩時間が勤務時間外です'
        ]);

        $followResponse = $this->get(route('attendance.detail', ['id' => $attendance->id]));
        $followResponse->assertSee('休憩時間が勤務時間外です');
    }

    /**
     * 勤怠詳細画面で備考欄が未入力の場合、バリデーションエラーが表示されることを確認するテスト
     *
     * @return void
     */
    public function test_validation_error_occurs_when_remarks_is_empty_on_detail_submission()
    {
        $adminUser = User::factory()->admin()->create();
        $this->actingAs($adminUser);

        $targetUser = User::factory()->create();
        $attendance = Attendance::factory()->create([
            'user_id' => $targetUser->id,
            'work_start' => '09:00:00',
            'work_end' => '18:00:00',
        ]);
        $rest1 = Rest::factory()->create([
            'attendance_id' => $attendance->id,
            'rest_start' => '12:00:00',
            'rest_end' => '13:00:00',
        ]);

        $response = $this->post(route('admin.attendance.request_correction', ['id' => $attendance->id]), [
            'work_start' => $attendance->work_start,
            'work_end' => $attendance->work_end,
            'remarks' => '',
            'rest_id' => [
                0 => $rest1->id,
                1 => '',
            ],
            'rest_start' => [
                0 => Carbon::parse($rest1->rest_start)->format('H:i'),
                1 => '',
            ],
            'rest_end' => [
                0 => Carbon::parse($rest1->rest_end)->format('H:i'),
                1 => '',
            ],
            '_token' => csrf_token(),
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('remarks');
        $response->assertSessionHasErrors([
            'remarks' => '備考を記入してください'
        ]);

        $followResponse = $this->get(route('attendance.detail', ['id' => $attendance->id]));
        $followResponse->assertSee('備考を記入してください');
    }
}
