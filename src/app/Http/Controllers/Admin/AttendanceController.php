<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;
use App\Models\Attendance;
use App\Models\Application;
use Carbon\Carbon;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\ApplicationRequest;
use Illuminate\Validation\ValidationException;

class AttendanceController extends Controller
{
    public function login()
    {
        return view('admin.login');
    }

    public function authenticate(LoginRequest $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            $user = Auth::user();

            if ($user && $user->isAdmin()) {
                return redirect()->intended('/admin/attendance/list');
            } else {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                throw ValidationException::withMessages([
                    'email' => '管理者権限がありません。',
                ]);
            }
        }

        throw ValidationException::withMessages([
            'email' => 'ログイン情報が登録されていません',
        ]);
    }

    public function attendance_list(Request $request, $dateString = null)
    {
        $targetDate = $dateString ? Carbon::parse($dateString) : Carbon::today();
        $targetDateString = $targetDate->format('Y-m-d');

        $attendances = Attendance::with(['user', 'rests'])
            ->where('date', $targetDateString)
            ->orderBy('user_id')
            ->get();

        $previousDate = $targetDate->copy()->subDay()->format('Y-m-d');
        $nextDate = $targetDate->copy()->addDay()->format('Y-m-d');

        return view('admin.attendance_list', [
            'attendances' => $attendances,
            'displayDate' => $targetDate->format('Y年m月d日'),
            'currentDate' => $targetDateString,
            'previousDate' => $previousDate,
            'nextDate' => $nextDate,
        ]);
    }

    public function attendance_detail($id)
    {
        $attendance = Attendance::with(['user', 'rests'])
                          ->findOrFail($id);

        $viewData = [
            'attendance' => $attendance,
            'displayRests' => $attendance->rests ?? collect(),
        ];

        return view('admin.attendance_detail', $viewData);
    }

    public function requestCorrection(ApplicationRequest $request, $id)
    {
        $attendance = Attendance::findOrFail($id);

        $correctedRestsData = [];
        $restStarts = $request->input('rest_start', []);
        $restEnds = $request->input('rest_end', []);

        foreach ($restStarts as $index => $startTime) {
            $endTime = Arr::get($restEnds, $index);

            if (!empty($startTime) && !empty($endTime)) {
                try {
                    $startCarbon = Carbon::parse($startTime);
                    $endCarbon = Carbon::parse($endTime);

                    if ($endCarbon->gte($startCarbon)) {
                        $correctedRestsData[] = [
                            'start' => $startCarbon->format('H:i'),
                            'end' => $endCarbon->format('H:i'),
                        ];
                    } else {
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        $correctedWorkStart = null;
        $correctedWorkEnd = null;
        try {
            $workStartInput = $request->input('work_start');
            if (!empty($workStartInput)) {
                $correctedWorkStart = Carbon::parse($workStartInput)->format('H:i');
            }
            $workEndInput = $request->input('work_end');
            if (!empty($workEndInput)) {
                $correctedWorkEnd = Carbon::parse($workEndInput)->format('H:i');
            }

            if ($correctedWorkStart && $correctedWorkEnd && Carbon::parse($correctedWorkStart)->gt(Carbon::parse($correctedWorkEnd))) {
            }

        } catch (\Exception $e) {
        }

        try {
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

            return redirect()->route('attendance.detail', $id)
                             ->withErrors(['application_error' => '申請処理中にエラーが発生しました。'])
                             ->withInput();
        }
    }
}
