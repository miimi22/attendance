<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;
use Carbon\Carbon;
use Symfony\Component\DomCrawler\Crawler;

class AttendanceWorkTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     * 勤怠画面で「出勤」ボタンが表示され、押下するとステータスが「勤務中」になることを確認するテスト
     */
    public function work_start_button_is_displayed_and_changes_status_to_working_on_click()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $today = Carbon::today(config('app.timezone'))->toDateString();

        $attendancePageRoute = route('attendance');
        $workStartRoute = route('attendance.workstart');

        $response = $this->get($attendancePageRoute);
        $response->assertOk();

        $response->assertSee('出勤');

        $postResponse = $this->post($workStartRoute);

        $postResponse->assertRedirect($attendancePageRoute);

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'date' => $today,
        ]);

        $attendanceRecord = Attendance::where('user_id', $user->id)->where('date', $today)->first();
        $this->assertNotNull($attendanceRecord, '出勤記録がデータベースに見つかりませんでした。');
        $this->assertNotNull($attendanceRecord->work_start, '出勤時刻 (work_start) が記録されていません。');
        $this->assertNull($attendanceRecord->work_end, '退勤時刻 (work_end) が記録されているべきではありません。');

        $responseAfterAction = $this->get($attendancePageRoute);
        $responseAfterAction->assertOk();

        $crawler = new Crawler($responseAfterAction->content());
        $statusText = null;
        if ($crawler->filter('div.contents > div.situation')->count() > 0) {
            $statusText = trim($crawler->filter('div.contents > div.situation')->text());
        }
        $this->assertNotNull($statusText, '画面から勤怠ステータス情報が見つかりませんでした（出勤処理後）。');
        $this->assertEquals('出勤中', $statusText, '勤怠ステータスが「勤務中」と表示されていません（出勤処理後）。実際の表示: ' . $statusText);
    }

    /**
     * @test
     * 勤怠画面で、ユーザーがその日に出勤できるのは１回のみ、「出勤」ボタンが２回表示されないことを確認するテスト
     */
    public function work_start_button_is_not_displayed_if_user_has_already_clocked_out_for_the_day()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $today = Carbon::today(config('app.timezone'))->toDateString();
        $currentTime = Carbon::now(config('app.timezone'));

        Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => $today,
            'work_start' => $currentTime->copy()->subHours(9),
            'work_end' => $currentTime->copy()->subHour(),
        ]);

        $attendancePageRoute = route('attendance');
        $response = $this->get($attendancePageRoute);

        $response->assertOk();

        $response->assertDontSee('出勤');
    }

    /**
     * @test
     * 勤怠一覧画面に出勤時刻が正確に記録（表示）されていることを確認するテスト
     */
    public function displays_correct_work_start_time_on_attendance_list()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $now = Carbon::now(config('app.timezone'));

        $workStartRoute = route('attendance.workstart');
        $responseFromWorkStart = $this->post($workStartRoute);

        $responseFromWorkStart->assertRedirect();

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'date' => $now->toDateString(),
        ]);
        $attendanceRecord = Attendance::where('user_id', $user->id)->where('date', $now->toDateString())->first();
        $this->assertNotNull($attendanceRecord, '出勤記録がデータベースに作成されていません。');
        $actualWorkStartTime = Carbon::parse($attendanceRecord->work_start, config('app.timezone'));


        $attendanceListRoute = route('attendance.list');
        $response = $this->get($attendanceListRoute);

        $response->assertOk();

        $crawler = new Crawler($response->content());

        $expectedDateDisplay = $now->format('m/d') . '(' . $now->isoFormat('ddd') . ')';

        $rowContainingDate = $crawler->filter('table.attendance-list tbody tr')->filterXPath(
            sprintf("//td[contains(@class, 'date-value') and normalize-space(text()) = '%s']", $expectedDateDisplay)
        )->closest('tr');


        $this->assertTrue($rowContainingDate->count() > 0, "勤怠一覧に今日 ({$expectedDateDisplay}) の記録が見つかりませんでした。");

        $displayedWorkStartTimeString = trim($rowContainingDate->filter('td.work-start-value')->text());

        $this->assertEquals(
            $actualWorkStartTime->format('H:i'),
            $displayedWorkStartTimeString,
            "勤怠一覧に表示された出勤時刻が記録と一致しません。期待値: " . $actualWorkStartTime->format('H:i') . ", 表示: {$displayedWorkStartTimeString}"
        );
    }
}
