<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Application;
use App\Models\Rest;
use Carbon\Carbon;
use Illuminate\Support\Str;

class ApplicationApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
    }

    public function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * 管理者の申請一覧画面で「承認待ち」タブに全ての承認待ち申請が表示されることを確認するテスト
     *
     * @return void
     */
    public function test_admin_can_view_all_pending_applications_on_pending_tab()
    {
        $adminUser = User::factory()->admin()->create();
        $this->actingAs($adminUser);

        $applicantUser1 = User::factory()->create(['name' => '申請者 一郎']);
        $attendance1 = Attendance::factory()->create(['user_id' => $applicantUser1->id, 'date' => Carbon::parse('2025-04-01')]);
        $pendingApplication1 = Application::factory()->create([
            'attendance_id' => $attendance1->id,
            'date' => $attendance1->date,
            'remarks' => '電車遅延のため、始業時刻を修正願います。',
            'accepted' => 0,
            'created_at' => Carbon::parse('2025-04-02 10:00:00'),
        ]);

        $applicantUser2 = User::factory()->create(['name' => '申請者 次郎']);
        $attendance2 = Attendance::factory()->create(['user_id' => $applicantUser2->id, 'date' => Carbon::parse('2025-04-03')]);
        $pendingApplication2 = Application::factory()->create([
            'attendance_id' => $attendance2->id,
            'date' => $attendance2->date,
            'remarks' => '退勤打刻を忘れました。',
            'accepted' => 0,
            'created_at' => Carbon::parse('2025-04-03 18:00:00'),
        ]);

        $applicantUser3 = User::factory()->create(['name' => '申請者 三郎']);
        $attendance3 = Attendance::factory()->create(['user_id' => $applicantUser3->id, 'date' => Carbon::parse('2025-04-05')]);
        $approvedApplication = Application::factory()->create([
            'attendance_id' => $attendance3->id,
            'date' => $attendance3->date,
            'remarks' => '承認済みテスト申請',
            'accepted' => 1,
        ]);

        $response = $this->get(route('application.list', ['status' => 'pending']));

        $response->assertStatus(200);
        $response->assertViewIs('admin.application_list');

        $response->assertSee('<title>申請一覧画面（管理者）</title>', false);
        $response->assertSee('<h1 class="application-list-title">申請一覧</h1>', false);

        $response->assertSee('href="' . route('application.list', ['status' => 'pending']) . '" class="pending-approval active"', false);
        $response->assertSee('href="' . route('application.list', ['status' => 'approved']) . '" class="approved "', false);

        $response->assertSeeInOrder(['状態', '名前', '対象日時', '申請理由', '申請日時', '詳細']);

        $response->assertSeeInOrder([
            '承認待ち',
            $applicantUser1->name,
            $pendingApplication1->date->format('Y/m/d'),
            Str::limit($pendingApplication1->remarks, 50),
            $pendingApplication1->created_at->format('Y/m/d'),
        ], false);
        $response->assertSee('href="' . url('/stamp_correction_request/approve/' . $pendingApplication1->id) . '"', false);


        $response->assertSeeInOrder([
            '承認待ち',
            $applicantUser2->name,
            $pendingApplication2->date->format('Y/m/d'),
            Str::limit($pendingApplication2->remarks, 50),
            $pendingApplication2->created_at->format('Y/m/d'),
        ], false);
        $response->assertSee('href="' . url('/stamp_correction_request/approve/' . $pendingApplication2->id) . '"', false);


        $response->assertDontSee($applicantUser3->name);
        $response->assertDontSee(Str::limit($approvedApplication->remarks, 50));
    }

    /**
     * 管理者の申請一覧画面で「承認済み」タブに全ての承認済み申請が表示されることを確認するテスト
     *
     * @return void
     */
    public function test_admin_can_view_all_approved_applications_on_approved_tab()
    {
        $adminUser = User::factory()->admin()->create();
        $this->actingAs($adminUser);

        $applicantUser1 = User::factory()->create(['name' => '承認済 一郎']);
        $attendance1 = Attendance::factory()->create(['user_id' => $applicantUser1->id, 'date' => Carbon::parse('2025-05-01')]);
        $approvedApplication1 = Application::factory()->create([
            'attendance_id' => $attendance1->id,
            'date' => $attendance1->date,
            'remarks' => '承認済みの申請その1です。',
            'accepted' => 1,
            'created_at' => Carbon::parse('2025-05-02 11:00:00'),
        ]);

        $applicantUser2 = User::factory()->create(['name' => '承認済 次郎']);
        $attendance2 = Attendance::factory()->create(['user_id' => $applicantUser2->id, 'date' => Carbon::parse('2025-05-03')]);
        $approvedApplication2 = Application::factory()->create([
            'attendance_id' => $attendance2->id,
            'date' => $attendance2->date,
            'remarks' => 'これも承認済みの申請です。',
            'accepted' => 1,
            'created_at' => Carbon::parse('2025-05-04 12:00:00'),
        ]);

        $applicantUser3 = User::factory()->create(['name' => '承認待ち 三郎']);
        $attendance3 = Attendance::factory()->create(['user_id' => $applicantUser3->id, 'date' => Carbon::parse('2025-05-05')]);
        $pendingApplication = Application::factory()->create([
            'attendance_id' => $attendance3->id,
            'date' => $attendance3->date,
            'remarks' => 'これは承認待ちの申請です。',
            'accepted' => 0,
        ]);

        $response = $this->get(route('application.list', ['status' => 'approved']));

        $response->assertStatus(200);
        $response->assertViewIs('admin.application_list');

        $response->assertSee('href="' . route('application.list', ['status' => 'pending']) . '" class="pending-approval "', false);
        $response->assertSee('href="' . route('application.list', ['status' => 'approved']) . '" class="approved active"', false);

        $response->assertSeeInOrder([
            '承認済み',
            $applicantUser1->name,
            $approvedApplication1->date->format('Y/m/d'),
            Str::limit($approvedApplication1->remarks, 50),
            $approvedApplication1->created_at->format('Y/m/d'),
        ], false);
        $response->assertSee('href="' . url('/stamp_correction_request/approve/' . $approvedApplication1->id) . '"', false);

        $response->assertSeeInOrder([
            '承認済み',
            $applicantUser2->name,
            $approvedApplication2->date->format('Y/m/d'),
            Str::limit($approvedApplication2->remarks, 50),
            $approvedApplication2->created_at->format('Y/m/d'),
        ], false);
        $response->assertSee('href="' . url('/stamp_correction_request/approve/' . $approvedApplication2->id) . '"', false);

        $response->assertDontSee($applicantUser3->name);
        $response->assertDontSee(Str::limit($pendingApplication->remarks, 50));
    }

    /**
     * 管理者の申請一覧の「詳細」から修正申請承認画面に遷移し、申請内容が正しく表示されることを確認するテスト
     *
     * @return void
     */
    public function test_admin_can_navigate_to_approval_screen_and_view_application_details()
    {
        $adminUser = User::factory()->admin()->create();
        $this->actingAs($adminUser);

        $applicant = User::factory()->create(['name' => '申請 花子']);
        $attendance = Attendance::factory()->create([
            'user_id' => $applicant->id,
            'date' => Carbon::parse('2025-06-10'),
            'work_start' => '09:00:00',
            'work_end' => '18:00:00',
        ]);
        $originalRest = Rest::factory()->create([
            'attendance_id' => $attendance->id,
            'rest_start' => '12:00:00',
            'rest_end' => '13:00:00',
        ]);

        $application = Application::factory()->create([
            'attendance_id' => $attendance->id,
            'date' => $attendance->date,
            'remarks' => '会議のため1時間早く退勤しました。休憩時間も修正願います。',
            'corrected_work_start' => '09:05:00',
            'corrected_work_end' => '17:00:00',
            'corrected_rests' => json_encode([
                ['start' => '12:00', 'end' => '12:45']
            ]),
            'accepted' => 0,
        ]);

        $response = $this->get(route('admin.application.show.legacy', ['attendance_correct_request' => $application->id]));

        $response->assertStatus(200);
        $response->assertViewIs('admin.application_approval');

        $response->assertSee('<title>修正申請承認画面（管理者）</title>', false);
        $response->assertSee('<h1 class="attendance-detail-title">勤怠詳細</h1>', false);

        $response->assertSee($applicant->name);
        $response->assertSee($application->date->format('Y年'));
        $response->assertSee($application->date->format('n月j日'));

        $response->assertSeeInOrder([
            '<td class="work">出勤・退勤</td>',
            '<div class="work-start">' . Carbon::parse($application->corrected_work_start)->format('H:i') . '</div>',
            '<div class="mark">～</div>',
            '<div class="work-end">' . Carbon::parse($application->corrected_work_end)->format('H:i') . '</div>'
        ], false);


        $response->assertSeeInOrder([
            '休憩',
            Carbon::parse($originalRest->rest_start)->format('H:i'),
            '～',
            Carbon::parse($originalRest->rest_end)->format('H:i')
        ], false);
        $response->assertSee('<div class="rest-start">', false);
        $response->assertSee(Carbon::parse($originalRest->rest_start)->format('H:i'));
        $response->assertSee('<div class="rest-end">', false);
        $response->assertSee(Carbon::parse($originalRest->rest_end)->format('H:i'));


        $response->assertSee($application->remarks);

        $response->assertSee('<button type="submit" class="approval-button">承認</button>', false);
        $response->assertSee('action="' . route('admin.application.approve.legacy', ['attendance_correct_request' => $application->id]) . '"', false);
    }

    /**
     * 管理者の修正申請承認画面で「承認」を押下すると申請が承認され勤怠情報が更新されることを確認するテスト
     *
     * @return void
     */
    public function test_admin_can_approve_application_and_attendance_is_updated()
    {
        $adminUser = User::factory()->admin()->create();
        $this->actingAs($adminUser);

        $applicant = User::factory()->create();
        $originalAttendance = Attendance::factory()->create([
            'user_id' => $applicant->id,
            'date' => Carbon::parse('2025-07-15'),
            'work_start' => '09:00:00',
            'work_end' => '18:00:00',
        ]);
        $originalRest1 = Rest::factory()->create([
            'attendance_id' => $originalAttendance->id,
            'rest_start' => '12:00:00',
            'rest_end' => '13:00:00',
        ]);
        $originalRest2 = Rest::factory()->create([
            'attendance_id' => $originalAttendance->id,
            'rest_start' => '15:00:00',
            'rest_end' => '15:15:00',
        ]);

        $correctedWorkStart = '09:05:00';
        $correctedWorkEnd = '17:30:00';
        $correctedRestsData = [
            ['start' => '12:15', 'end' => '13:00'],
            ['start' => '16:00', 'end' => '16:10'],
        ];

        $application = Application::factory()->create([
            'attendance_id' => $originalAttendance->id,
            'date' => $originalAttendance->date,
            'remarks' => '出退勤時刻と休憩時間を修正願います。',
            'corrected_work_start' => $correctedWorkStart,
            'corrected_work_end' => $correctedWorkEnd,
            'corrected_rests' => json_encode($correctedRestsData),
            'accepted' => 0,
        ]);

        $response = $this->patch(route('admin.application.approve.legacy', ['attendance_correct_request' => $application->id]));

        $response->assertStatus(302);

        $updatedApplication = Application::find($application->id);
        $this->assertEquals(1, $updatedApplication->accepted);

        $updatedAttendance = Attendance::find($originalAttendance->id);
        $this->assertEquals($correctedWorkStart, Carbon::parse($updatedAttendance->work_start)->format('H:i:s'));
        $this->assertEquals($correctedWorkEnd, Carbon::parse($updatedAttendance->work_end)->format('H:i:s'));

        $this->assertDatabaseMissing('rests', ['id' => $originalRest2->id]);

        $newRests = Rest::where('attendance_id', $updatedAttendance->id)->get();
        $this->assertCount(count($correctedRestsData), $newRests);

        foreach ($correctedRestsData as $index => $correctedRest) {
            $this->assertEquals(
                Carbon::parse($correctedRest['start'])->format('H:i:s'),
                Carbon::parse($newRests[$index]->rest_start)->format('H:i:s')
            );
            $this->assertEquals(
                Carbon::parse($correctedRest['end'])->format('H:i:s'),
                Carbon::parse($newRests[$index]->rest_end)->format('H:i:s')
            );
        }
    }
}
