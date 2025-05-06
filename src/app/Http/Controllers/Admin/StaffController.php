<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffController extends Controller
{
    public function staff_list()
    {
        $users = User::where('role', 0)->get();

        return view('admin.staff_list', compact('users'));
    }

    public function staff_attendance_list($id, $yearMonth = null)
    {
        $user = User::findOrFail($id);

        try {
            $targetDate = $yearMonth ? Carbon::createFromFormat('Y-m', $yearMonth)->startOfMonth() : Carbon::now()->startOfMonth();
        } catch (\Exception $e) {
            $targetDate = Carbon::now()->startOfMonth();
            Log::info('Invalid yearMonth format in admin staff attendance list for user ID ' . $id . ': ' . $yearMonth);
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

        return view('admin.staff_attendance_list', compact(
            'user',
            'attendances',
            'displayMonth',
            'prevMonth',
            'nextMonth',
            'targetDate'
        ));
    }

    public function exportCsv($id, $yearMonth)
    {
        $user = User::findOrFail($id);

        try {
            $targetDate = Carbon::createFromFormat('Y-m', $yearMonth)->startOfMonth();
        } catch (\Exception $e) {
            Log::warning('Invalid yearMonth format for CSV export for user ID ' . $id . ': ' . $yearMonth);
            $targetDate = Carbon::now()->startOfMonth();
        }

        $attendances = Attendance::where('user_id', $user->id)
            ->whereYear('date', $targetDate->year)
            ->whereMonth('date', $targetDate->month)
            ->with('rests')
            ->orderBy('date', 'asc')
            ->get();

        $csvFileName = $user->name . '_' . $targetDate->format('Ym') . '_attendance.csv';

        $response = new StreamedResponse(function () use ($attendances) {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");

            $header = [
                '日付',
                '曜日',
                '出勤時刻',
                '退勤時刻',
                '休憩時間合計',
                '実働時間合計',
            ];
            fputcsv($handle, $header);

            foreach ($attendances as $attendance) {
                $totalRestTimeFormatted = $attendance->total_rest_time;

                $row = [
                    $attendance->date->format('Y/m/d'),
                    $attendance->date->isoFormat('ddd'),
                    $attendance->work_start ? Carbon::parse($attendance->work_start)->format('H:i') : '-',
                    $attendance->work_end ? Carbon::parse($attendance->work_end)->format('H:i') : '-',
                    $totalRestTimeFormatted,
                    $attendance->total_work ? Carbon::parse($attendance->total_work)->format('H:i') : '-',
                ];
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $csvFileName . '"',
        ]);

        return $response;
    }
}
