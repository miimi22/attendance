<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Application;
use App\Models\Attendance;
use App\Models\Rest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ApplicationController extends Controller
{
    public function application_list(Request $request)
    {
        $status = $request->input('status', 'pending');

        $query = Application::with(['attendance.user'])
                            ->orderBy('created_at', 'desc');

        if ($status === 'approved') {
            $query->where('accepted', 1);
        } else {
            $query->where('accepted', 0);
            $status = 'pending';
        }

        $applications = $query->get();

        return view('admin.application_list', compact(
            'applications',
            'status'
        ));
    }

    public function application_approval($applicationId)
    {
        try {
            $application = Application::with(['attendance.user', 'attendance.rests'])
                                        ->findOrFail($applicationId);

            $applicationDate = ($application->date instanceof Carbon) ? $application->date : Carbon::parse($application->date);

            $correctedRestsArray = [];
            if (!is_null($application->corrected_rests)) {
                if (is_string($application->corrected_rests)) {
                    $decoded = json_decode($application->corrected_rests, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $correctedRestsArray = $decoded;
                    } else {
                        Log::warning("申請ID {$application->id} の corrected_rests のJSONデコードに失敗しました。値: " . $application->corrected_rests);
                    }
                } elseif (is_array($application->corrected_rests)) {
                    $correctedRestsArray = $application->corrected_rests;
                } else {
                    Log::warning("申請ID {$application->id} の corrected_rests が予期しない型です。型: " . gettype($application->corrected_rests));
                }
            }

            $displayData = [
                'name' => optional(optional($application->attendance)->user)->name ?? 'N/A',
                'date' => $applicationDate,
                'work_start' => $application->corrected_work_start ?? optional($application->attendance)->work_start,
                'work_end' => $application->corrected_work_end ?? optional($application->attendance)->work_end,
                'corrected_rests' => $correctedRestsArray,
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
            Log::error("申請詳細表示エラー (ID: {$applicationId}): " . $e->getMessage() . " Trace: " . $e->getTraceAsString());
            return redirect()->route('application.list')->with('error', '申請詳細の表示中にエラーが発生しました。');
        }
    }

    public function approve($applicationId)
    {
        try {
            $application = Application::findOrFail($applicationId);

            if ($application->accepted !== 0) {
                return redirect()->route('application.list', ['status' => $application->accepted === 1 ? 'approved' : 'pending'])
                                ->with('warning', 'この申請は既に処理済みか、承認待ちではありません。');
            }

            DB::beginTransaction();
            try {
                $application->accepted = 1;
                $application->save();
                Log::info("申請ID: {$application->id} の accepted ステータスを1に更新し保存試行。");

                $attendance = $application->attendance;
                if ($attendance) {
                    Log::info("申請ID: {$application->id} の関連勤怠ID: {$attendance->id} を取得。");
                    if (!is_null($application->corrected_work_start)) {
                        $attendance->work_start = $application->corrected_work_start;
                        Log::info("勤怠ID: {$attendance->id} の work_start を {$attendance->work_start} に更新。");
                    }
                    if (!is_null($application->corrected_work_end)) {
                        $attendance->work_end = $application->corrected_work_end;
                        Log::info("勤怠ID: {$attendance->id} の work_end を {$attendance->work_end} に更新。");
                    }

                    if (!is_null($application->corrected_rests)) {
                        $correctedRestsArray = null;
                        if (is_string($application->corrected_rests)) {
                            $correctedRestsArray = json_decode($application->corrected_rests, true);
                            if (json_last_error() !== JSON_ERROR_NONE) {
                                Log::warning("申請ID {$application->id} の corrected_rests のJSONデコードに失敗しました (approveメソッド)。値: " . $application->corrected_rests);
                                $correctedRestsArray = null;
                            }
                        } elseif (is_array($application->corrected_rests)) {
                            $correctedRestsArray = $application->corrected_rests;
                        } else {
                            Log::warning("申請ID {$application->id} の corrected_rests が予期しない型です (approveメソッド)。型: " . gettype($application->corrected_rests));
                        }

                        Log::info("申請ID: {$application->id} の corrected_rests を処理。結果: ", $correctedRestsArray ?: ['データなし/デコード失敗/元々配列']);

                        if (is_array($correctedRestsArray)) {
                            Log::info("勤怠ID: {$attendance->id} の既存休憩を削除します。");
                            $attendance->rests()->delete();
                            foreach ($correctedRestsArray as $index => $restData) {
                                if (!empty($restData['start']) && !empty($restData['end'])) {
                                    Log::info("勤怠ID: {$attendance->id} の {$index}番目の新規休憩を作成: ", $restData);
                                    Rest::create([
                                        'attendance_id' => $attendance->id,
                                        'rest_start' => $restData['start'],
                                        'rest_end' => $restData['end'],
                                    ]);
                                } else {
                                    Log::info("勤怠ID: {$attendance->id} の {$index}番目の新規休憩データは start または end が空です。", $restData);
                                }
                            }
                        } else {
                            Log::warning("申請ID {$application->id} の corrected_rests は配列形式でないか、JSONデコードに失敗したため、休憩時間は更新されませんでした。元の値: " . print_r($application->corrected_rests, true));
                        }
                    }
                    Log::info("勤怠ID: {$attendance->id} の変更を保存します。");
                    $attendance->save();
                    Log::info("勤怠ID: {$attendance->id} の保存完了。");

                } else {
                    Log::warning("承認処理: 申請ID {$application->id} に関連する勤怠レコードが見つかりません。ロールバックします。");
                    DB::rollBack();
                    return redirect()->route('application.list', ['status' => 'pending'])
                                    ->with('error', '関連する勤怠データが見つからないため、承認処理を中断しました。');
                }

                DB::commit();
                Log::info("申請ID: {$application->id} が承認され、トランザクションがコミットされました。");
                return redirect()->route('application.list', ['status' => 'approved'])
                                ->with('success', '申請を承認し、勤怠データを更新しました。');

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("申請承認エラー (ID: {$application->id}): " . $e->getMessage() . " Trace: " . $e->getTraceAsString());
                return redirect()->route('application.list', ['status' => 'pending'])
                                ->with('error', '申請の承認処理中にエラーが発生しました。');
            }

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::warning("申請承認エラー: ID {$applicationId} が見つかりません。");
            return redirect()->route('application.list')->with('error', '指定された申請が見つかりません。');
        } catch (\Exception $e) {
            Log::error("申請承認エラー (ID: {$applicationId}): " . $e->getMessage() . " Trace: " . $e->getTraceAsString());
            return redirect()->route('application.list')->with('error', '申請の承認中に予期せぬエラーが発生しました。');
        }
    }
}
