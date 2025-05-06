<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Rest;
use App\Models\Application;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;
use App\Http\Requests\ApplicationRequest;

class AttendanceController extends Controller
{
    public function attendance()
    {
        $user = Auth::user();
        $status = $this->getCurrentStatus($user);

        if ($user->getRawOriginal('attendance_status') != $status) {
             $user->attendance_status = $status;
             $user->save();
        }

        $currentTime = Carbon::now()->format('H:i');
        $currentDate = Carbon::now()->format('Y年m月d日');
        $currentDayOfWeek = ['日', '月', '火', '水', '木', '金', '土'][Carbon::now()->dayOfWeek];

        return view('attendance', compact('status', 'user', 'currentTime', 'currentDate', 'currentDayOfWeek'));
    }

    private function getCurrentStatus(User $user): int
    {
        $todayAttendance = Attendance::where('user_id', $user->id)
            ->where('date', today())
            ->latest('id')
            ->first();

        if (!$todayAttendance) {
             $lastAttendance = Attendance::where('user_id', $user->id)->latest('id')->first();
             return User::STATUS_OFF_WORK;
        }

        if ($todayAttendance->work_end === null) {
            $activeBreak = Rest::where('attendance_id', $todayAttendance->id)
                ->whereNull('rest_end')
                ->latest('id')
                ->first();

            return $activeBreak ? User::STATUS_ON_REST : User::STATUS_ON_WORK;
        } else {
            return User::STATUS_LEFT_WORK;
        }
    }

    public function workStart(Request $request)
    {
        $user = Auth::user();
        $status = $this->getCurrentStatus($user);

        if ($status == User::STATUS_OFF_WORK || $status == User::STATUS_LEFT_WORK) {
            $existingAttendance = Attendance::where('user_id', $user->id)
                ->where('date', today())
                ->whereNull('work_end')
                ->first();

            if (!$existingAttendance) {
                Attendance::create([
                    'user_id' => $user->id,
                    'date' => today(),
                    'work_start' => now()->format('H:i:s'),
                ]);
                $user->attendance_status = User::STATUS_ON_WORK;
                $user->save();
                return redirect()->route('attendance')->with('status', '出勤しました。');
            } else {
                 $user->attendance_status = User::STATUS_ON_WORK;
                 $user->save();
                 return redirect()->route('attendance')->withErrors(['clock_in' => '既に出勤済みです。']);
            }
        }
        return redirect()->route('attendance')->withErrors(['clock_in' => '出勤処理を実行できません。']);
    }

    public function workEnd(Request $request)
    {
        $user = Auth::user();
        $attendance = Attendance::where('user_id', $user->id)
            ->where('date', today())
            ->whereNull('work_end')
            ->latest('id')
            ->first();

        if (!$attendance) {
            return redirect()->route('attendance')->withErrors(['clock_out' => '退勤記録が見つかりません。']);
        }

        $activeBreak = Rest::where('attendance_id', $attendance->id)
            ->whereNull('rest_end')
            ->latest('id')
            ->first();
        if ($activeBreak) {
            return redirect()->route('attendance')->withErrors(['clock_out' => '休憩中です。先に休憩終了してください。']);
        }

        $workEndTime = null;

        try {
            $workStartDate = $attendance->date->format('Y-m-d');
            $workStartTime = Carbon::parse($workStartDate . ' ' . $attendance->work_start);
            $workEndTime = Carbon::now();

            if ($workEndTime->lessThan($workStartTime)) {
                 $attendance->total_work = '00:00:00';
            } else {
                $totalBreakSeconds = 0;
                $breaks = Rest::where('attendance_id', $attendance->id)
                                ->whereNotNull('rest_end')
                                ->get();

                foreach ($breaks as $break) {
                    if ($break->rest_start && $break->rest_end) {
                        $breakStartTime = Carbon::parse($workStartDate . ' ' . $break->rest_start);
                        $breakEndTime = Carbon::parse($workStartDate . ' ' . $break->rest_end);
                        if ($breakEndTime->greaterThan($breakStartTime)) {
                             $totalBreakSeconds += $breakEndTime->diffInSeconds($breakStartTime);
                        }
                    }
                }

                $totalWorkSeconds = $workEndTime->diffInSeconds($workStartTime) - $totalBreakSeconds;
                $totalWorkSeconds = max(0, $totalWorkSeconds);
                $attendance->total_work = gmdate('H:i:s', $totalWorkSeconds);
             }

             if ($workEndTime) {
                $attendance->work_end = $workEndTime->format('H:i:s');
                $saveResult = $attendance->save();
                if (!$saveResult) {
                    throw new \Exception('勤怠レコードの保存に失敗しました。');
                }
            } else {
                throw new \Exception('退勤時刻を記録できませんでした。');
            }

        } catch (\Exception $e) {
            return redirect()->route('attendance')->withErrors(['clock_out' => '退勤処理中にエラーが発生しました: ' . $e->getMessage()]);
        }

        $user->attendance_status = User::STATUS_LEFT_WORK;
        $saveUserResult = $user->save();

        if (!$saveUserResult) {
            return redirect()->route('attendance')->withErrors(['clock_out' => 'ユーザー情報の更新に失敗しました。勤怠データは記録されましたが、管理者に連絡してください。']);
        }
        return redirect()->route('attendance')->with('status', '退勤しました。');
    }

    public function restStart(Request $request)
    {
        $user = Auth::user();
        $attendance = Attendance::where('user_id', $user->id)
            ->where('date', today())
            ->whereNull('work_end')
            ->latest('id')
            ->first();

        if ($attendance && $this->getCurrentStatus($user) == User::STATUS_ON_WORK) {
             $activeBreak = Rest::where('attendance_id', $attendance->id)
                ->whereNull('rest_end')
                ->latest('id')
                ->first();

            if (!$activeBreak) {
                Rest::create([
                    'attendance_id' => $attendance->id,
                    'rest_start' => now()->format('H:i:s'),
                ]);
                $user->attendance_status = User::STATUS_ON_REST;
                $user->save();
                return redirect()->route('attendance')->with('status', '休憩を開始しました。');
            }
        }
        return redirect()->route('attendance')->withErrors(['break_start' => '休憩開始処理を実行できません。']);
    }

    public function restEnd(Request $request)
    {
        $user = Auth::user();
        $attendance = Attendance::where('user_id', $user->id)
            ->where('date', today())
            ->whereNull('work_end')
            ->latest('id')
            ->first();

        if ($attendance) {
            $activeBreak = Rest::where('attendance_id', $attendance->id)
                ->whereNull('rest_end')
                ->latest('id')
                ->first();

            if ($activeBreak) {
                $activeBreak->rest_end = now()->format('H:i:s');
                $activeBreak->save();

                $user->attendance_status = User::STATUS_ON_WORK;
                $user->save();
                return redirect()->route('attendance')->with('status', '休憩を終了しました。');
            }
        }
        return redirect()->route('attendance')->withErrors(['break_end' => '休憩終了処理を実行できません。']);
    }

    public function attendance_list($yearMonth = null)
    {
        $user = Auth::user();

        try {
            $targetDate = $yearMonth ? Carbon::createFromFormat('Y-m', $yearMonth)->startOfMonth() : Carbon::now()->startOfMonth();
        } catch (\Exception $e) {
            $targetDate = Carbon::now()->startOfMonth();
            Log::info('Invalid yearMonth format received: ' . $yearMonth);
        }

        $displayMonth = $targetDate->format('Y/m');
        $prevMonth = $targetDate->copy()->subMonth()->format('Y-m');
        $nextMonth = $targetDate->copy()->addMonth()->format('Y-m');

        $attendances = Attendance::where('user_id', $user->id)
            ->whereYear('date', $targetDate->year)
            ->whereMonth('date', $targetDate->month)
            ->with('rests')
            ->orderBy('date', 'asc')
            ->get();

        return view('attendance_list', compact(
            'attendances',
            'displayMonth',
            'prevMonth',
            'nextMonth'
        ));
    }

    public function attendance_detail($id)
    {
        $attendance = Attendance::with(['user', 'rests', 'applications'])
                            ->where('user_id', Auth::id())
                            ->findOrFail($id);

        $pendingApplication = $attendance->applications()
                                    ->where('accepted', 0)
                                    ->latest('created_at')
                                    ->first();

        $isPendingApproval = !is_null($pendingApplication);

        $viewData = [
            'attendance' => $attendance,
            'pendingApplication' => $pendingApplication,
            'isPendingApproval' => $isPendingApproval,
        ];

        if ($isPendingApproval && !empty($pendingApplication->corrected_rests)) {
             $viewData['displayRests'] = $pendingApplication->corrected_rests;
        } else {
             $viewData['displayRests'] = $attendance->rests;
        }

        if (!isset($viewData['displayRests']) || is_null($viewData['displayRests'])) {
            $viewData['displayRests'] = collect();
        }

        return view('attendance_detail', $viewData);
    }

    public function requestCorrection(ApplicationRequest $request, $id)
    {
        $attendance = Attendance::where('user_id', Auth::id())->findOrFail($id);

        $correctedRestsData = [];
        $restStarts = $request->input('rest_start', []);
        $restEnds = $request->input('rest_end', []);

        foreach ($restStarts as $index => $startTime) {
            $endTime = Arr::get($restEnds, $index);

            if (!empty($startTime) && !empty($endTime)) {
                try {
                    $startFormatted = Carbon::parse($startTime)->format('H:i');
                    $endFormatted = Carbon::parse($endTime)->format('H:i');

                    if (Carbon::parse($startFormatted)->lte(Carbon::parse($endFormatted))) {
                       $correctedRestsData[] = [
                           'start' => $startFormatted,
                           'end' => $endFormatted,
                       ];
                    } else {
                       Log::warning('Rest time error: start is after end.', [
                           'attendance_id' => $id,
                           'index' => $index,
                           'start' => $startTime,
                           'end' => $endTime
                       ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to parse rest time input.', [
                        'attendance_id' => $id,
                        'index' => $index,
                        'start_input' => $startTime,
                        'end_input' => $endTime,
                        'error' => $e->getMessage(),
                    ]);
                    continue;
                }
            }
        }

        try {
            $workStartInput = $request->input('work_start');
            $workEndInput = $request->input('work_end');
            $correctedWorkStart = null;
            $correctedWorkEnd = null;

            try {
                if (!empty($workStartInput)) {
                    $correctedWorkStart = Carbon::parse($workStartInput)->format('H:i');
                }
                if (!empty($workEndInput)) {
                    $correctedWorkEnd = Carbon::parse($workEndInput)->format('H:i');
                }

                if ($correctedWorkStart && $correctedWorkEnd && Carbon::parse($correctedWorkStart)->gt(Carbon::parse($correctedWorkEnd))) {
                     Log::warning('Work time error: start is after end.', [
                         'attendance_id' => $id,
                         'start' => $workStartInput,
                         'end' => $workEndInput
                     ]);
                }

            } catch (\Exception $e) {
                 Log::error('Failed to parse work time input.', [
                    'attendance_id' => $id,
                    'start_input' => $workStartInput,
                    'end_input' => $workEndInput,
                    'error' => $e->getMessage(),
                ]);
            }

            Application::create([
                'attendance_id' => $attendance->id,
                'date' => $attendance->date,
                'remarks' => $request->input('remarks'),
                'accepted' => 0,
                'corrected_work_start' => $correctedWorkStart,
                'corrected_work_end' => $correctedWorkEnd,
                'corrected_rests' => !empty($correctedRestsData) ? $correctedRestsData : null,
            ]);

            return redirect()->route('application.list')
                             ->with('status', '勤怠修正を申請しました。');

        } catch (\Exception $e) {
            Log::error('Failed to create attendance correction request in database.', [
                'attendance_id' => $id,
                'user_id_auth' => Auth::id(),
                'submitted_request_data' => $request->except(['password', '_token']),
                'prepared_rests_array' => $correctedRestsData,
                'prepared_work_start' => $correctedWorkStart,
                'prepared_work_end' => $correctedWorkEnd,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('attendance.detail', $id)
                             ->withErrors(['application_error' => '申請処理中に予期せぬエラーが発生しました。時間をおいて再度お試しいただくか、管理者にお問い合わせください。'])
                             ->withInput();
        }
    }
}
