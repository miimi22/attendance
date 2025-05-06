<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Application;
use App\Models\Attendance;
use App\Models\Rest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ApplicationController extends Controller
{
    public function application_list(Request $request)
    {
        $status = $request->input('status', 'pending');

        $query = Application::with(['attendance.user'])
                            ->orderBy('created_at', 'desc');

        if ($status === 'approved') {
            $query->where('accepted', 1);
            $pageTitle = '承認済み申請一覧';
        } else {
            $query->where('accepted', 0);
            $pageTitle = '承認待ち申請一覧';
            $status = 'pending';
        }

        $applications = $query->get();

        return view('admin.application_list', compact(
            'applications',
            'status',
        ));
    }

    public function application_approval($applicationId)
    {
        try {
            $application = Application::with(['attendance.user', 'attendance.rests'])
                                        ->findOrFail($applicationId);

            $displayData = [
                'name' => optional(optional($application->attendance)->user)->name ?? 'N/A',
                'date' => $application->date,
                'work_start' => $application->corrected_work_start ?? optional($application->attendance)->work_start,
                'work_end' => $application->corrected_work_end ?? optional($application->attendance)->work_end,
                'corrected_rests' => $application->corrected_rests,
                'original_rests' => optional($application->attendance)->rests,
                'remarks' => $application->remarks,
                'original_work_start' => optional($application->attendance)->work_start,
                'original_work_end' => optional($application->attendance)->work_end,
            ];

            return view('admin.application_approval', compact('application', 'displayData'));

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::warning("申請詳細表示エラー: ID {$applicationId} が見つかりません。");
            return redirect()->route('application.list')->with('error', '指定された申請が見つかりません。');
        } catch (\Exception $e) {
            Log::error("申請詳細表示エラー (ID: {$applicationId}): " . $e->getMessage());
            return redirect()->route('application.list')->with('error', '申請詳細の表示中にエラーが発生しました。');
        }
    }

    public function approve($applicationId)
    {
        try {
            $application = Application::findOrFail($applicationId);

            if ($application->accepted !== 0) {
                return redirect()->route('application.list', ['status' => 'approved'])
                                 ->with('warning', 'この申請は既に処理済みです。');
            }

            DB::beginTransaction();
            try {
                $application->accepted = 1;
                $application->save();

                $attendance = $application->attendance;
                if ($attendance) {
                    if (!is_null($application->corrected_work_start)) $attendance->work_start = $application->corrected_work_start;
                    if (!is_null($application->corrected_work_end)) $attendance->work_end = $application->corrected_work_end;

                    if (!is_null($application->corrected_rests)) {
                        $attendance->rests()->delete();
                        foreach ($application->corrected_rests as $restData) {
                            if (!empty($restData['start']) && !empty($restData['end'])) {
                                Rest::create([
                                    'attendance_id' => $attendance->id,
                                    'rest_start' => $restData['start'],
                                    'rest_end' => $restData['end'],
                                ]);
                            }
                        }
                    }
                    $attendance->save();
                } else {
                    Log::warning("承認処理: 申請ID {$application->id} に関連する勤怠レコードが見つかりません。ロールバックします。");
                    DB::rollBack();
                    return redirect()->route('admin.application.show.legacy', ['applicationId' => $applicationId])
                                     ->with('error', '関連する勤怠データが見つからないため、承認処理を中断しました。');
                }

                DB::commit();
                Log::info("申請ID: {$application->id} が承認されました。");
                return redirect()->route('application.list', ['status' => 'approved'])
                                 ->with('status', '申請を承認し、勤怠データを更新しました。');

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("申請承認エラー (ID: {$application->id}): " . $e->getMessage());
                return redirect()->route('admin.application.show.legacy', ['applicationId' => $applicationId])
                                 ->with('error', '申請の承認処理中にエラーが発生しました。');
            }

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
             Log::warning("申請承認エラー: ID {$applicationId} が見つかりません。");
             return redirect()->route('application.list')->with('error', '指定された申請が見つかりません。');
        } catch (\Exception $e) {
             Log::error("申請承認エラー (ID: {$applicationId}): " . $e->getMessage());
             return redirect()->route('application.list')->with('error', '申請の承認中に予期せぬエラーが発生しました。');
        }
    }
}
