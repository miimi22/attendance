<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Rest;
use Carbon\Carbon;
use Symfony\Component\DomCrawler\Crawler;

class LeavingWorkTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     * 出勤中のユーザーが勤怠画面で「退勤」ボタンを押し、ステータスが「退勤済」になることを確認するテスト
     */
    public function user_can_work_end_and_status_changes_to_left_work(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $testBaseTime = Carbon::create(2025, 5, 8, 9, 0, 0, config('app.timezone'));
        Carbon::setTestNow($testBaseTime);

        $todayDateString = $testBaseTime->toDateString();
        $workStartTime = $testBaseTime;

        $attendanceData = [
            'user_id' => $user->id,
            'date' => $todayDateString,
            'work_start' => $workStartTime->format('H:i:s'),
            'work_end' => null,
            'total_work' => null,
        ];

        $attendance = Attendance::factory()->create($attendanceData);

        $attendancePageRoute = route('attendance');
        $response = $this->get($attendancePageRoute);
        $response->assertOk();

        $crawler = new Crawler($response->content());
        $statusText = null;
        if ($crawler->filter('div.contents > div.situation')->count() > 0) {
            $statusText = trim($crawler->filter('div.contents > div.situation')->text());
        }
        $this->assertNotNull($statusText, '画面から勤怠ステータス情報が見つかりませんでした（退勤前）。');
        $this->assertEquals('出勤中', $statusText, '勤怠ステータスが「出勤中」と表示されていません。実際の表示: ' . $statusText);

        $response->assertSee('<button type="submit" class="attendance-button work-end-button">退勤</button>', false);

        $workEndTime = $testBaseTime->copy()->addHours(8);
        Carbon::setTestNow($workEndTime);

        $workEndRoute = route('attendance.workend');
        $postResponse = $this->post($workEndRoute);

        $postResponse->assertSessionHasNoErrors();
        $postResponse->assertRedirect($attendancePageRoute);

        $attendance->refresh();
        $this->assertNotNull($attendance->work_end, '退勤時刻 (work_end) が記録されていません。');
        $this->assertEquals($workEndTime->format('H:i:s'), $attendance->work_end, '退勤時刻が正しく記録されていません。');
        $this->assertNotNull($attendance->total_work, '総労働時間 (total_work) が記録されていません（NULLは不可）。');

        $responseAfterAction = $this->get($attendancePageRoute);
        $responseAfterAction->assertOk();

        $crawlerAfterAction = new Crawler($responseAfterAction->content());
        $statusTextAfterAction = null;
        if ($crawlerAfterAction->filter('div.contents > div.situation')->count() > 0) {
            $statusTextAfterAction = trim($crawlerAfterAction->filter('div.contents > div.situation')->text());
        }
        $this->assertNotNull($statusTextAfterAction, '画面から勤怠ステータス情報が見つかりませんでした（退勤後）。');
        $this->assertEquals('退勤済', $statusTextAfterAction, '勤怠ステータスが「退勤済」と表示されていません。実際の表示: ' . $statusTextAfterAction);

        $responseAfterAction->assertSee('お疲れ様でした。');
        $responseAfterAction->assertDontSee('<button type="submit" class="attendance-button work-end-button">退勤</button>', false);

        Carbon::setTestNow();
    }

    /**
     * @test
     * 勤務外のユーザーが出勤・退勤を行い、勤怠一覧に退勤時間が記録されることを確認するテスト
     */
    public function off_work_user_can_start_and_end_work_and_it_is_recorded_on_attendance_list(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $testDate = Carbon::create(2025, 5, 9, 0, 0, 0, config('app.timezone'));


        $workStartTime = $testDate->copy()->setHour(9)->setMinute(0)->setSecond(0);
        Carbon::setTestNow($workStartTime);

        $workStartRoute = route('attendance.workstart');
        $postResponseWorkStart = $this->post($workStartRoute);

        $postResponseWorkStart->assertSessionHasNoErrors();
        $postResponseWorkStart->assertRedirect(route('attendance'));

        $attendance = Attendance::where('user_id', $user->id)
            ->where('date', $testDate->toDateString())
            ->latest('id')
            ->first();

        $this->assertNotNull($attendance, '出勤記録が作成されていません。');
        $this->assertEquals($workStartTime->format('H:i:s'), $attendance->work_start, '出勤開始時刻が正しくありません。');
        $this->assertNull($attendance->work_end, '出勤時には退勤時刻は記録されていないはずです。');

        $workEndTime = $testDate->copy()->setHour(18)->setMinute(0)->setSecond(0);
        Carbon::setTestNow($workEndTime);

        $workEndRoute = route('attendance.workend');
        $postResponseWorkEnd = $this->post($workEndRoute);

        $postResponseWorkEnd->assertSessionHasNoErrors();
        $postResponseWorkEnd->assertRedirect(route('attendance'));

        $attendance->refresh();
        $this->assertNotNull($attendance->work_end, '退勤時刻 (work_end) が記録されていません。');
        $this->assertEquals($workEndTime->format('H:i:s'), $attendance->work_end, '退勤時刻が正しく記録されていません。');
        $this->assertNotNull($attendance->total_work, '総労働時間 (total_work) が記録されていません（NULLは不可）。');

        $attendanceListRoute = route('attendance.list');
        $responseList = $this->get($attendanceListRoute);

        $responseList->assertOk();
        $responseList->assertViewIs('attendance_list');
        $responseList->assertViewHas('attendances');

        $expectedDateDisplay = $testDate->format('m/d') . '(' . $testDate->isoFormat('ddd') . ')';
        $expectedWorkStartDisplay = $workStartTime->format('H:i');
        $expectedWorkEndDisplay = $workEndTime->format('H:i');
        $expectedRestTimeDisplay = '00:00';

        $responseList->assertSeeInOrder([
            '<td class="date-value">' . $expectedDateDisplay . '</td>',
            '<td class="work-start-value">' . $expectedWorkStartDisplay . '</td>',
            '<td class="work-end-value">' . $expectedWorkEndDisplay . '</td>',
            '<td class="rest-value">' . $expectedRestTimeDisplay . '</td>',
            '<a href="' . route('attendance.detail', ['id' => $attendance->id]) . '" class="detail-value">詳細</a>'
        ], false);

        $responseList->assertSee('<td class="work-end-value">' . $expectedWorkEndDisplay . '</td>', false);

        Carbon::setTestNow();
    }
}
