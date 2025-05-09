<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Application;
use Carbon\Carbon;
use App\Rules\WithinWorkTime;
use Illuminate\Support\Str;

class AttendanceDetailCorrectionTest extends TestCase
{
    use RefreshDatabase; // 各テスト実行前にデータベースをリフレッシュ

    /**
     * 出勤時間が退勤時間より後の場合、バリデーションエラーメッセージが表示されることを確認するテスト
     *
     * @return void
     */
    public function test_validation_error_message_is_shown_when_work_start_is_after_work_end()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => Carbon::create(2025, 10, 10)->toDateString(),
            'work_start' => '09:00:00',
            'work_end' => '18:00:00',
            'total_work' => '08:00:00',
        ]);

        $invalidWorkStartTime = '20:00';
        $validWorkEndTime = '19:00';
        $requiredRemarks = 'これはテスト用の備考です。';

        $postData = [
            'work_start' => $invalidWorkStartTime,
            'work_end' => $validWorkEndTime,
            'remarks' => $requiredRemarks,
            'rest_id' => ['0' => ''],
            'rest_start' => ['0' => ''],
            'rest_end' => ['0' => ''],
        ];

        $response = $this->post(route('attendance.request_correction', ['id' => $attendance->id]), $postData);

        $response->assertSessionHasErrors([
            'work_start' => '出勤時間もしくは退勤時間が不適切な値です'
        ]);

        $response->assertRedirect();
    }

    /**
     * 休憩開始時間が退勤時間より後の場合、バリデーションメッセージが表示されるテスト
     *
     * @return void
     */
    public function test_validation_error_when_rest_start_is_after_work_end_time()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => Carbon::create(2025, 10, 11)->toDateString(),
            'work_start' => '09:00:00',
            'work_end' => '18:00:00',
            'total_work' => '08:00:00',
        ]);

        $validWorkStartTime = '09:00';
        $validWorkEndTime = '18:00';

        $invalidRestStartTime = '18:30';
        $validRestEndTime = '19:00';

        $requiredRemarks = 'テスト用の備考（必須）。';

        $postData = [
            'work_start' => $validWorkStartTime,
            'work_end' => $validWorkEndTime,
            'remarks' => $requiredRemarks,
            'rest_id' => ['0' => ''],
            'rest_start' => ['0' => $invalidRestStartTime],
            'rest_end' => ['0' => $validRestEndTime],
        ];

        $response = $this->post(route('attendance.request_correction', ['id' => $attendance->id]), $postData);

        $expectedErrorMessage = '休憩時間が勤務時間外です';


        $response->assertSessionHasErrors([
            'rest_time_range.0' => $expectedErrorMessage
        ]);

        $response->assertRedirect();
    }

    /**
     * 休憩終了時間が退勤時間より後の場合、バリデーションメッセージが表示されるテスト
     *
     * @return void
     */
    public function test_validation_error_when_rest_end_time_is_after_work_end_time()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => Carbon::create(2025, 10, 13)->toDateString(),
            'work_start' => '09:00:00',
            'work_end' => '18:00:00',
            'total_work' => '08:00:00',
        ]);

        $validWorkStartTime = '09:00';
        $validWorkEndTime = '18:00';

        $validRestStartTime = '17:00';
        $invalidRestEndTime = '18:30';

        $requiredRemarks = 'テスト用の備考（必須）。';

        $postData = [
            'work_start' => $validWorkStartTime,
            'work_end' => $validWorkEndTime,
            'remarks' => $requiredRemarks,
            'rest_id' => ['0' => ''],
            'rest_start' => ['0' => $validRestStartTime],
            'rest_end' => ['0' => $invalidRestEndTime],
        ];

        $response = $this->post(route('attendance.request_correction', ['id' => $attendance->id]), $postData);

        $expectedErrorMessage = '休憩時間が勤務時間外です';

        $response->assertSessionHasErrors([
            'rest_time_range.0' => $expectedErrorMessage
        ]);

        $response->assertRedirect();
    }

    /**
     * 備考欄が未入力の場合、バリデーションメッセージが表示されるテスト
     *
     * @return void
     */
    public function test_validation_error_when_remarks_field_is_empty()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => Carbon::create(2025, 10, 14)->toDateString(),
            'work_start' => '09:00:00',
            'work_end' => '18:00:00',
            'total_work' => '08:00:00',
        ]);

        $validWorkStartTime = '09:00';
        $validWorkEndTime = '18:00';
        $validRestStartTime = '12:00';
        $validRestEndTime = '13:00';

        $postData = [
            'work_start' => $validWorkStartTime,
            'work_end' => $validWorkEndTime,
            'remarks' => '',
            'rest_id' => ['0' => ''],
            'rest_start' => ['0' => $validRestStartTime],
            'rest_end' => ['0' => $validRestEndTime],
        ];

        $response = $this->post(route('attendance.request_correction', ['id' => $attendance->id]), $postData);

        $response->assertSessionHasErrors([
            'remarks' => '備考を記入してください'
        ]);

        $response->assertRedirect();
    }

    /**
     * 修正申請処理が実行され、管理者の承認画面と申請一覧画面に表示されることを確認するテスト
     *
     * @return void
     */
    public function test_correction_application_is_processed_and_visible_to_admin()
    {
        $generalUser = User::factory()->create(['name' => '申請 太郎', 'role' => 0]);
        $this->actingAs($generalUser);

        $attendanceDate = Carbon::create(2025, 11, 15);
        $originalAttendance = Attendance::factory()->create([
            'user_id' => $generalUser->id,
            'date' => $attendanceDate->toDateString(),
            'work_start' => '09:00:00',
            'work_end' => '18:00:00',
            'total_work' => '08:00:00',
        ]);
        $originalRest = $originalAttendance->rests()->create([
            'rest_start' => '12:00:00',
            'rest_end' => '13:00:00',
        ]);

        $correctedWorkStartTime = '09:30';
        $correctedWorkEndTime = '18:30';
        $correctedRest1Start = '12:15';
        $correctedRest1End = '12:45';
        $applicationRemarks = '電車遅延による時刻修正と休憩時間変更の申請です。';

        $postData = [
            'work_start' => $correctedWorkStartTime,
            'work_end' => $correctedWorkEndTime,
            'remarks' => $applicationRemarks,
            'rest_id' => [
                '0' => $originalRest->id,
            ],
            'rest_start' => [
                '0' => $correctedRest1Start,
            ],
            'rest_end' => [
                '0' => $correctedRest1End,
            ],
        ];

        $responseGeneralUser = $this->post(route('attendance.request_correction', ['id' => $originalAttendance->id]), $postData);

        $responseGeneralUser->assertRedirect();

        $this->assertDatabaseHas('applications', [
            'attendance_id' => $originalAttendance->id,
            'date' => $attendanceDate->toDateString(),
            'remarks' => $applicationRemarks,
            'corrected_work_start' => $correctedWorkStartTime . ':00',
            'corrected_work_end' => $correctedWorkEndTime . ':00',
            'accepted' => 0,
        ]);

        $application = Application::where('attendance_id', $originalAttendance->id)->latest()->first();
        $this->assertNotNull($application, '申請レコードが作成されていません。');
        $this->assertIsArray($application->corrected_rests, 'corrected_rests が配列ではありません。');
        $this->assertCount(1, $application->corrected_rests, 'corrected_rests の要素数が1ではありません。');
        $this->assertEquals($correctedRest1Start, $application->corrected_rests[0]['start'] ?? null);
        $this->assertEquals($correctedRest1End, $application->corrected_rests[0]['end'] ?? null);


        $adminUser = User::factory()->create(['name' => '管理 一郎', 'role' => 1]);
        $this->actingAs($adminUser);

        $responseAdminList = $this->get(route('application.list', ['status' => 'pending']));
        $responseAdminList->assertStatus(200);
        $responseAdminList->assertViewIs('admin.application_list');
        $responseAdminList->assertSee('申請一覧');
        $responseAdminList->assertSee('承認待ち');
        $responseAdminList->assertSee($generalUser->name);
        $responseAdminList->assertSee($application->date->format('Y/m/d'));
        $responseAdminList->assertSee(Str::limit($applicationRemarks, 50));
        $responseAdminList->assertSee($application->created_at->format('Y/m/d'));
        $adminDetailLink = url('/stamp_correction_request/approve/' . $application->id);
        $responseAdminList->assertSee($adminDetailLink);

        $responseAdminApproval = $this->get($adminDetailLink);
        $responseAdminApproval->assertStatus(200);
        $responseAdminApproval->assertViewIs('admin.application_approval');
        $responseAdminApproval->assertViewHas('application', function ($viewApp) use ($application) {
            return $viewApp instanceof Application && $viewApp->id === $application->id;
        });

        $responseAdminApproval->assertSee($generalUser->name);
        $responseAdminApproval->assertSee($application->date->format('Y年'));
        $responseAdminApproval->assertSee($application->date->format('n月j日'));
        $responseAdminApproval->assertSee(Carbon::parse($application->corrected_work_start)->format('H:i'));
        $responseAdminApproval->assertSee(Carbon::parse($application->corrected_work_end)->format('H:i'));
        if (is_array($application->corrected_rests) && count($application->corrected_rests) > 0) {
            $responseAdminApproval->assertSee(Carbon::parse($application->corrected_rests[0]['start'])->format('H:i'));
            $responseAdminApproval->assertSee(Carbon::parse($application->corrected_rests[0]['end'])->format('H:i'));
        }
        $responseAdminApproval->assertSee($applicationRemarks);

        $responseAdminApproval->assertSee('承認</button>', false);
        $responseAdminApproval->assertSee('action="' . route('admin.application.approve.legacy', ['attendance_correct_request' => $application->id]) . '"', false);
    }

    /**
     * 申請一覧画面の「承認待ち」タブに、ログインユーザーの承認待ち申請が全て表示されることを確認するテスト
     *
     * @return void
     */
    public function test_displays_all_pending_applications_for_logged_in_user_on_pending_tab()
    {
        $applicantUser = User::factory()->create(['name' => '申請者 テスト']);
        $otherUser = User::factory()->create(['name' => '他人 テスト']);

        $attendance1 = Attendance::factory()->create([
            'user_id' => $applicantUser->id,
            'date' => Carbon::create(2025, 1, 10)->toDateString(),
            'work_start' => '09:00:00',
            'work_end' => '18:00:00',
        ]);
        $application1 = Application::factory()->create([
            'attendance_id' => $attendance1->id,
            'date' => $attendance1->date,
            'remarks' => '申請1：承認待ちの備考です。',
            'corrected_work_start' => '09:05:00',
            'corrected_work_end' => '18:05:00',
            'corrected_rests' => json_encode([['start' => '12:00', 'end' => '13:00']]),
            'accepted' => 0,
            'created_at' => Carbon::create(2025, 1, 11, 10, 0, 0),
        ]);

        $attendance2 = Attendance::factory()->create([
            'user_id' => $applicantUser->id,
            'date' => Carbon::create(2025, 1, 12)->toDateString(),
            'work_start' => '10:00:00',
            'work_end' => '19:00:00',
        ]);
        $application2 = Application::factory()->create([
            'attendance_id' => $attendance2->id,
            'date' => $attendance2->date,
            'remarks' => '申請2：これも承認待ちの備考です。長めの文章もテストします。ああああああいいいいいいううううううええええええおおおおおお。',
            'corrected_work_start' => '10:15:00',
            'corrected_work_end' => '19:15:00',
            'corrected_rests' => null,
            'accepted' => 0,
            'created_at' => Carbon::create(2025, 1, 13, 11, 0, 0),
        ]);

        $attendance3 = Attendance::factory()->create([
            'user_id' => $applicantUser->id,
            'date' => Carbon::create(2025, 1, 14)->toDateString(),
        ]);
        Application::factory()->create([
            'attendance_id' => $attendance3->id,
            'date' => $attendance3->date,
            'remarks' => '申請3：承認済みの備考。',
            'accepted' => 1,
        ]);

        $otherUserAttendance = Attendance::factory()->create([
            'user_id' => $otherUser->id,
            'date' => Carbon::create(2025, 1, 15)->toDateString(),
        ]);
        Application::factory()->create([
            'attendance_id' => $otherUserAttendance->id,
            'date' => $otherUserAttendance->date,
            'remarks' => '他人ユーザーの承認待ち申請。',
            'accepted' => 0,
        ]);


        $this->actingAs($applicantUser);

        $response = $this->get(route('application.list', ['status' => 'pending']));

        $response->assertStatus(200);
        $response->assertViewIs('application_list');
        $response->assertViewHas('applications');
        $response->assertViewHas('statusFilter', 'pending');

        $response->assertSee($application1->status_text);
        $response->assertSee($applicantUser->name);
        $response->assertSee($application1->formatted_subject_date);
        $response->assertSee($application1->remarks);
        $response->assertSee($application1->formatted_application_date);
        $response->assertSee(route('attendance.detail', ['id' => $application1->attendance_id]));

        $response->assertSee($application2->status_text);
        $response->assertSee($application2->formatted_subject_date);
        $response->assertSee($application2->remarks);
        $response->assertSee($application2->formatted_application_date);
        $response->assertSee(route('attendance.detail', ['id' => $application2->attendance_id]));

        $response->assertDontSee('申請3：承認済みの備考。');

        $response->assertDontSee('他人ユーザーの承認待ち申請。');
        $response->assertDontSee($otherUser->name);

        $response->assertDontSee('承認待ちの申請はありません。');
    }

    /**
     * 申請一覧画面「承認済み」タブに、ログインユーザー自身の承認済み申請が全て表示されているのを確認するテスト
     *
     * @return void
     */
    public function test_general_user_can_see_their_own_approved_applications_on_approved_tab()
    {
        $applicantUser = User::factory()->create(['name' => '申請者 本人', 'role' => 0]);
        $anotherUser = User::factory()->create(['name' => '他人 ユーザー', 'role' => 0]);

        $attendance1 = Attendance::factory()->create(['user_id' => $applicantUser->id, 'date' => Carbon::create(2025, 3, 1)->toDateString()]);
        $application1 = Application::factory()->create([
            'attendance_id' => $attendance1->id,
            'date' => $attendance1->date,
            'remarks' => '本人申請1：承認済みです。',
            'accepted' => 1,
            'created_at' => Carbon::create(2025, 3, 2, 9, 0, 0),
        ]);

        $attendance2 = Attendance::factory()->create(['user_id' => $applicantUser->id, 'date' => Carbon::create(2025, 3, 3)->toDateString()]);
        $application2 = Application::factory()->create([
            'attendance_id' => $attendance2->id,
            'date' => $attendance2->date,
            'remarks' => '本人申請2：これも承認済み。',
            'accepted' => 1,
            'created_at' => Carbon::create(2025, 3, 4, 10, 0, 0),
        ]);

        $attendance3 = Attendance::factory()->create(['user_id' => $applicantUser->id, 'date' => Carbon::create(2025, 3, 5)->toDateString()]);
        Application::factory()->create([
            'attendance_id' => $attendance3->id,
            'date' => $attendance3->date,
            'remarks' => '本人申請3：承認待ち。',
            'accepted' => 0,
        ]);

        $anotherUserAttendance = Attendance::factory()->create(['user_id' => $anotherUser->id, 'date' => Carbon::create(2025, 3, 6)->toDateString()]);
        Application::factory()->create([
            'attendance_id' => $anotherUserAttendance->id,
            'date' => $anotherUserAttendance->date,
            'remarks' => '他人申請4：承認済み。',
            'accepted' => 1,
        ]);


        $this->actingAs($applicantUser);

        $response = $this->get(route('application.list', ['status' => 'approved']));

        $response->assertStatus(200);
        $response->assertViewIs('application_list');
        $response->assertViewHas('applications');
        $response->assertViewHas('statusFilter', 'approved');

        $response->assertSee($application1->status_text);
        $response->assertSee($applicantUser->name);
        $response->assertSee($application1->formatted_subject_date);
        $response->assertSee($application1->remarks);
        $response->assertSee($application1->formatted_application_date);
        $response->assertSee(route('attendance.detail', ['id' => $application1->attendance_id]));

        $response->assertSee($application2->status_text);
        $response->assertSee($application2->formatted_subject_date);
        $response->assertSee($application2->remarks);
        $response->assertSee($application2->formatted_application_date);
        $response->assertSee(route('attendance.detail', ['id' => $application2->attendance_id]));

        $response->assertDontSee('本人申請3：承認待ち。');

        $response->assertDontSee('他人申請4：承認済み。');
        $response->assertDontSee($anotherUser->name);

        $response->assertDontSee('承認済みの申請はありません。');
    }

    /**
     * 申請一覧画面の「詳細」リンクから勤怠詳細画面に遷移することを確認するテスト
     *
     * @return void
     */
    public function test_detail_link_on_user_application_list_redirects_to_attendance_detail()
    {
        $user = User::factory()->create(['name' => '詳細確認ユーザー', 'role' => 0]);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => Carbon::create(2025, 4, 10)->toDateString(),
            'work_start' => '09:00:00',
            'work_end' => '18:00:00',
        ]);
        $application = Application::factory()->create([
            'attendance_id' => $attendance->id,
            'date' => $attendance->date,
            'remarks' => '詳細確認用の承認済み申請です。',
            'accepted' => 1,
            'created_at' => Carbon::now(),
        ]);

        $this->actingAs($user);
        $responseList = $this->get(route('application.list', ['status' => 'approved']));
        $responseList->assertStatus(200);
        $responseList->assertViewIs('application_list');

        $expectedDetailLink = route('attendance.detail', ['id' => $application->attendance_id]);
        $responseList->assertSee($expectedDetailLink);

        $responseDetail = $this->get($expectedDetailLink);

        $responseDetail->assertStatus(200);
        $responseDetail->assertViewIs('attendance_detail');
        $responseDetail->assertViewHas('attendance', function ($viewAttendance) use ($attendance) {
            return $viewAttendance instanceof Attendance && $viewAttendance->id === $attendance->id;
        });

        $responseDetail->assertSee($attendance->date->format('Y年'), false);
        $responseDetail->assertSee($attendance->date->format('n月j日'), false);
        $responseDetail->assertSee($user->name);
    }
}
