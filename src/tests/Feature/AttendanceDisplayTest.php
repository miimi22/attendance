<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use Carbon\Carbon;
use Symfony\Component\DomCrawler\Crawler;

class AttendanceDisplayTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     * 勤怠画面に表示される日時が現在のシステム日時と一致することを確認するテスト
     */
    public function displayed_datetime_on_attendance_page_matches_current_datetime()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $attendancePageRoute = route('attendance');

        $response = $this->get($attendancePageRoute);
        $response->assertOk();

        $crawler = new Crawler($response->content());
        $dateStringRaw = null;
        $timeStringRaw = null;

        if ($crawler->filter('div.datetime-info > div.date')->count() > 0) {
            $dateStringRaw = trim($crawler->filter('div.datetime-info > div.date')->text());
        }
        if ($crawler->filter('div.datetime-info > div.time')->count() > 0) {
            $timeStringRaw = trim($crawler->filter('div.datetime-info > div.time')->text());
        }

        $this->assertNotNull($dateStringRaw, '画面から日付情報 (div.date) が見つかりませんでした。');
        $this->assertNotNull($timeStringRaw, '画面から時刻情報 (div.time) が見つかりませんでした。');

        preg_match('/(\d{4})年(\d{2})月(\d{2})日/', $dateStringRaw, $dateMatches);

        if (count($dateMatches) !== 4) {
            $this->fail("日付のフォーマットが期待通りではありませんでした。取得した日付: {$dateStringRaw}");
        }
        $year = $dateMatches[1];
        $month = $dateMatches[2];
        $day = $dateMatches[3];

        $displayedDateTimeString = "{$year}-{$month}-{$day} {$timeStringRaw}";

        $now = Carbon::now(config('app.timezone'));

        try {
            $displayedTime = Carbon::parse($displayedDateTimeString, config('app.timezone'));
        } catch (\Exception $e) {
            $this->fail("画面から取得・整形した日時文字列 '{$displayedDateTimeString}' のパースに失敗しました: " . $e->getMessage());
            return;
        }

        $this->assertEquals(
            $now->format('Y-m-d H:i'),
            $displayedTime->format('Y-m-d H:i'),
            "表示された日時（分まで）が現在日時（分まで）と一致しません。" .
            "表示: " . $displayedTime->format('Y-m-d H:i') . ", " .
            "現在: " . $now->format('Y-m-d H:i')
        );
    }
}
