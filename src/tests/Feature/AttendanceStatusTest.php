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

class AttendanceStatusTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     * 勤怠画面で、ユーザーが勤務外の場合にステータスが「勤務外」と表示されることを確認するテスト
     */
    public function displays_out_of_office_status_when_user_is_not_working()
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $attendancePageRoute = route('attendance');
        $response = $this->get($attendancePageRoute);

        $response->assertOk();

        $crawler = new Crawler($response->content());
        $statusText = null;

        if ($crawler->filter('div.contents > div.situation')->count() > 0) {
            $statusText = trim($crawler->filter('div.contents > div.situation')->text());
        }

        $this->assertNotNull($statusText, '画面から勤怠ステータス情報 (div.contents > div.situation) が見つかりませんでした。');

        $this->assertEquals('勤務外', $statusText, '勤怠ステータスが「勤務外」と表示されていません。実際の表示: ' . $statusText);
    }

    /**
     * @test
     * 勤怠画面で、ユーザーが出勤中の場合にステータスが「出勤中」と表示されることを確認するテスト
     */
    public function displays_working_status_when_user_is_working()
    {
        $user = User::factory()->create();

        $currentTime = Carbon::now(config('app.timezone'));

        Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => $currentTime->toDateString(),
            'work_start' => $currentTime->copy()->subHour(),
            'work_end' => null,
            'total_work' => null,
        ]);

        $this->actingAs($user);

        $attendancePageRoute = route('attendance');
        $response = $this->get($attendancePageRoute);

        $response->assertOk();

        $crawler = new Crawler($response->content());
        $statusText = null;

        if ($crawler->filter('div.contents > div.situation')->count() > 0) {
            $statusText = trim($crawler->filter('div.contents > div.situation')->text());
        }

        $this->assertNotNull($statusText, '画面から勤怠ステータス情報 (div.contents > div.situation) が見つかりませんでした。');

        $this->assertEquals('出勤中', $statusText, '勤怠ステータスが「出勤中」と表示されていません。実際の表示: ' . $statusText);
    }

    /**
     * @test
     * 勤怠画面で、ユーザーが休憩中の場合にステータスが「休憩中」と表示されることを確認するテスト
     */
    public function displays_on_break_status_when_user_is_on_break()
    {
        $user = User::factory()->create();
        $currentTime = Carbon::now(config('app.timezone'));

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => $currentTime->toDateString(),
            'work_start' => $currentTime->copy()->subHours(2),
            'work_end' => null,
        ]);

        Rest::create([
            'attendance_id' => $attendance->id,
            'rest_start' => $currentTime->copy()->subHour(),
            'rest_end' => null,
        ]);

        $this->actingAs($user);

        $attendancePageRoute = route('attendance');
        $response = $this->get($attendancePageRoute);

        $response->assertOk();

        $crawler = new Crawler($response->content());
        $statusText = null;

        if ($crawler->filter('div.contents > div.situation')->count() > 0) {
            $statusText = trim($crawler->filter('div.contents > div.situation')->text());
        }

        $this->assertNotNull($statusText, '画面から勤怠ステータス情報 (div.contents > div.situation) が見つかりませんでした。');
        $this->assertEquals('休憩中', $statusText, '勤怠ステータスが「休憩中」と表示されていません。実際の表示: ' . $statusText);
    }

    /**
     * @test
     * 勤怠画面で、ユーザーが退勤済の場合にステータスが「退勤済」と表示されることを確認するテスト
     */
    public function displays_off_work_status_when_user_is_off_work()
    {
        $user = User::factory()->create();
        $currentTime = Carbon::now(config('app.timezone'));

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => $currentTime->toDateString(),
            'work_start' => $currentTime->copy()->subHours(8),
            'work_end' => $currentTime->copy()->subHours(1),
        ]);

        $this->actingAs($user);

        $attendancePageRoute = route('attendance');
        $response = $this->get($attendancePageRoute);

        $response->assertOk();

        $crawler = new Crawler($response->content());
        $statusText = null;

        if ($crawler->filter('div.contents > div.situation')->count() > 0) {
            $statusText = trim($crawler->filter('div.contents > div.situation')->text());
        }

        $this->assertNotNull($statusText, '画面から勤怠ステータス情報 (div.contents > div.situation) が見つかりませんでした。');
        $this->assertEquals('退勤済', $statusText, '勤怠ステータスが「退勤済」と表示されていません。実際の表示: ' . $statusText);
    }
}
