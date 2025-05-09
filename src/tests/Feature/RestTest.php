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

class RestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     * 出勤中のユーザーが勤怠画面で「休憩入」ボタンを押し、ステータスが「休憩中」になることを確認するテスト
     */
    public function clicking_rest_start_button_changes_status_to_on_break_when_user_is_working()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $currentTime = Carbon::now(config('app.timezone'));
        $today = $currentTime->toDateString();

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => $today,
            'work_start' => $currentTime->copy()->subHour(),
            'work_end' => null,
        ]);

        $attendancePageRoute = route('attendance');
        $response = $this->get($attendancePageRoute);
        $response->assertOk();

        $response->assertSee('休憩入');

        $restStartRoute = route('attendance.reststart');
        $postResponse = $this->post($restStartRoute);

        $postResponse->assertRedirect($attendancePageRoute);

        $this->assertDatabaseHas('rests', [
            'attendance_id' => $attendance->id,
            'rest_end' => null,
        ]);

        $restRecord = Rest::where('attendance_id', $attendance->id)
                            ->whereNull('rest_end')
                            ->latest('rest_start')
                            ->first();
        $this->assertNotNull($restRecord, '休憩記録がデータベースに見つかりませんでした。');
        $this->assertNotNull($restRecord->rest_start, '休憩開始時刻 (rest_start) が記録されていません。');


        $responseAfterAction = $this->get($attendancePageRoute);
        $responseAfterAction->assertOk();

        $crawlerAfterAction = new Crawler($responseAfterAction->content());
        $statusText = null;
        if ($crawlerAfterAction->filter('div.contents > div.situation')->count() > 0) {
            $statusText = trim($crawlerAfterAction->filter('div.contents > div.situation')->text());
        }
        $this->assertNotNull($statusText, '画面から勤怠ステータス情報が見つかりませんでした（休憩開始処理後）。');
        $this->assertEquals('休憩中', $statusText, '勤怠ステータスが「休憩中」と表示されていません（休憩開始処理後）。実際の表示: ' . $statusText);
    }

    /**
     * @test
     * 休憩を開始し終了した後、再度「休憩入」ボタンが表示されることを確認するテスト
     */
    public function rest_start_button_is_displayed_again_after_starting_and_ending_a_rest_period()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $currentTime = Carbon::now(config('app.timezone'));
        $today = $currentTime->toDateString();

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => $today,
            'work_start' => $currentTime->copy()->subHours(2),
            'work_end' => null,
        ]);

        $attendancePageRoute = route('attendance');
        $restStartRoute = route('attendance.reststart');
        $restEndRoute = route('attendance.restend');

        $responseRestStart = $this->post($restStartRoute);
        $responseRestStart->assertRedirect($attendancePageRoute);

        $firstRestRecord = Rest::where('attendance_id', $attendance->id)
                                ->whereNotNull('rest_start')
                                ->whereNull('rest_end')
                                ->latest('rest_start')
                                ->first();
        $this->assertNotNull($firstRestRecord, '最初の休憩開始記録がデータベースに見つかりませんでした。');

        $responseRestEnd = $this->post($restEndRoute);
        $responseRestEnd->assertRedirect($attendancePageRoute);

        $firstRestRecord->refresh();
        $this->assertNotNull($firstRestRecord->rest_end, '最初の休憩終了時刻がデータベースに記録されていません。');

        $responseAfterRest = $this->get($attendancePageRoute);
        $responseAfterRest->assertOk();

        $responseAfterRest->assertSee('休憩入');

        $attendance->refresh();
        $this->assertNotNull($attendance->work_start, '出勤記録が消えています。');
        $this->assertNull($attendance->work_end, '休憩終了後、意図せず退勤扱いになっています。');
    }

    /**
     * @test
     * 休憩を開始し終了した後、勤怠ステータスが「出勤中」と表示されることを確認するテスト
     */
    public function status_displays_as_working_after_starting_and_ending_a_rest_period()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $currentTime = Carbon::now(config('app.timezone'));
        $today = $currentTime->toDateString();

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => $today,
            'work_start' => $currentTime->copy()->subHours(3),
            'work_end' => null,
        ]);

        $attendancePageRoute = route('attendance');
        $restStartRoute = route('attendance.reststart');
        $restEndRoute = route('attendance.restend');


        $responseRestStart = $this->post($restStartRoute);
        $responseRestStart->assertRedirect($attendancePageRoute);

        $restRecord = Rest::where('attendance_id', $attendance->id)
                            ->whereNotNull('rest_start')
                            ->whereNull('rest_end')
                            ->latest('rest_start')
                            ->first();
        $this->assertNotNull($restRecord, '休憩開始記録がデータベースに見つかりませんでした。');


        $responseRestEnd = $this->post($restEndRoute);
        $responseRestEnd->assertRedirect($attendancePageRoute);

        $restRecord->refresh();
        $this->assertNotNull($restRecord->rest_end, '休憩終了時刻がデータベースに記録されていません。');

        $attendance->refresh();
        $this->assertNotNull($attendance->work_start, '出勤記録の開始時刻が見つかりません。');
        $this->assertNull($attendance->work_end, '休憩終了後、意図せず退勤扱いになっています。');


        $responseAfterRestEnd = $this->get($attendancePageRoute);
        $responseAfterRestEnd->assertOk();

        $crawler = new Crawler($responseAfterRestEnd->content());
        $statusText = null;

        if ($crawler->filter('div.contents > div.situation')->count() > 0) {
            $statusText = trim($crawler->filter('div.contents > div.situation')->text());
        }

        $this->assertNotNull($statusText, '画面から勤怠ステータス情報 (div.contents > div.situation) が見つかりませんでした（休憩終了後）。');

        $this->assertEquals('出勤中', $statusText, '勤怠ステータスが「出勤中」と表示されていません（休憩終了後）。実際の表示: ' . $statusText);
    }

    /**
     * @test
     * 休憩を開始し終了した後、再度休憩を開始し「休憩戻」ボタンが表示されることを確認するテスト
     */
    public function rest_start_button_is_displayed_again_after_starting_and_ending_and_starting_a_rest_period()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $currentTime = Carbon::now(config('app.timezone'));
        $today = $currentTime->toDateString();

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => $today,
            'work_start' => $currentTime->copy()->subHours(2),
            'work_end' => null,
        ]);

        $attendancePageRoute = route('attendance');
        $restStartRoute = route('attendance.reststart');
        $restEndRoute = route('attendance.restend');


        $responseRestStart = $this->post($restStartRoute);
        $responseRestStart->assertRedirect($attendancePageRoute);

        $firstRestRecord = Rest::where('attendance_id', $attendance->id)
                                ->whereNotNull('rest_start')
                                ->whereNull('rest_end')
                                ->latest('rest_start')
                                ->first();
        $this->assertNotNull($firstRestRecord, '最初の休憩開始記録がデータベースに見つかりませんでした。');


        $responseRestEnd = $this->post($restEndRoute);
        $responseRestEnd->assertRedirect($attendancePageRoute);

        $firstRestRecord->refresh();
        $this->assertNotNull($firstRestRecord->rest_end, '最初の休憩終了時刻がデータベースに記録されていません。');

        $responseRestStart = $this->post($restStartRoute);
        $responseRestStart->assertRedirect($attendancePageRoute);

        $firstRestRecord = Rest::where('attendance_id', $attendance->id)
                                ->whereNotNull('rest_start')
                                ->whereNull('rest_end')
                                ->latest('rest_start')
                                ->first();
        $this->assertNotNull($firstRestRecord, '最初の休憩開始記録がデータベースに見つかりませんでした。');

        $responseAfterRest = $this->get($attendancePageRoute);
        $responseAfterRest->assertOk();

        $responseAfterRest->assertSee('休憩戻');

        $attendance->refresh();
        $this->assertNotNull($attendance->work_start, '出勤記録が消えています。');
        $this->assertNull($attendance->work_end, '休憩終了後、意図せず退勤扱いになっています。');
    }

    /**
     * @test
     * 出勤中のユーザーが休憩を取り、その休憩時間が勤怠一覧に正しく記録・表示されることを確認するテスト
     */
    public function user_can_take_break_and_it_is_recorded_and_displayed_correctly_on_attendance_list()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $today = Carbon::today();
        $workStartTime = $today->copy()->setHour(9)->setMinute(0)->setSecond(0);
        Carbon::setTestNow($workStartTime);

        $response = $this->post(route('attendance.workstart'));
        $response->assertRedirect(route('attendance'));

        $attendance = Attendance::where('user_id', $user->id)
            ->where('date', $today->toDateString())
            ->first();

        $this->assertNotNull($attendance, '出勤記録が作成されていません。');
        $this->assertEquals($workStartTime->format('H:i:s'), $attendance->work_start, '出勤開始時刻が正しくありません。');


        $restStartTime = $today->copy()->setHour(12)->setMinute(30)->setSecond(0);
        Carbon::setTestNow($restStartTime);

        $response = $this->post(route('attendance.reststart'));
        $response->assertRedirect(route('attendance'));

        $rest = Rest::where('attendance_id', $attendance->id)->latest()->first();
        $this->assertNotNull($rest, '休憩記録が作成されていません。');
        $this->assertEquals($restStartTime->format('H:i:s'), $rest->rest_start, '休憩開始時刻が正しくありません。');
        $this->assertNull($rest->rest_end, '休憩終了時刻が記録されています（休憩開始時点ではnullのはず）。');

        $restEndTime = $today->copy()->setHour(13)->setMinute(30)->setSecond(0);
        Carbon::setTestNow($restEndTime);

        $response = $this->post(route('attendance.restend'));
        $response->assertRedirect(route('attendance'));

        $rest->refresh();
        $this->assertEquals($restEndTime->format('H:i:s'), $rest->rest_end, '休憩終了時刻が正しく記録されていません。');


        $workEndTime = $today->copy()->setHour(18)->setMinute(0)->setSecond(0);
        Carbon::setTestNow($workEndTime);

        $response = $this->post(route('attendance.workend'));
        $response->assertRedirect(route('attendance'));

        $attendance->refresh();
        $this->assertNotNull($attendance->work_end, '退勤時刻が記録されていません。');
        $this->assertEquals($workEndTime->format('H:i:s'), $attendance->work_end, '退勤時刻が正しくありません。');
        $this->assertNotNull($attendance->total_work, '総労働時間が計算されていません。');
        $this->assertNotNull($attendance->total_rest_time, '総休憩時間が計算されていません。');

        $expectedRestTimeForDb = '01:00';
        $this->assertEquals($expectedRestTimeForDb, $attendance->total_rest_time, 'DBに保存された総休憩時間が正しくありません。');


        $response = $this->get(route('attendance.list'));
        $response->assertOk();
        $response->assertViewIs('attendance_list');
        $response->assertViewHas('attendances');

        $expectedDateDisplay = $today->format('m/d') . '(' . $today->isoFormat('ddd') . ')';
        $expectedRestTimeDisplay = Carbon::parse($attendance->total_rest_time)->format('H:i');

        $response->assertSeeInOrder([
            '<td class="date-value">' . $expectedDateDisplay . '</td>',
            '<td class="work-start-value">' . $workStartTime->format('H:i') . '</td>',
            '<td class="work-end-value">' . $workEndTime->format('H:i') . '</td>',
            '<td class="rest-value">' . $expectedRestTimeDisplay . '</td>',
            '<a href="' . route('attendance.detail', ['id' => $attendance->id]) . '" class="detail-value">詳細</a>'
        ], false);

        $response->assertSee('<td class="rest-value">' . $expectedRestTimeDisplay . '</td>', false);


        Carbon::setTestNow();
    }
}
