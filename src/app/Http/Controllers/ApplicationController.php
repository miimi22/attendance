<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Application;
use App\Models\Attendance;

class ApplicationController extends Controller
{
    public function application_list(Request $request)
    {
        $user = Auth::user();

        $attendanceIds = Attendance::where('user_id', $user->id)->pluck('id');

        $statusFilter = $request->query('status');

        $query = Application::with(['attendance.user'])
            ->whereIn('attendance_id', $attendanceIds);

        if ($statusFilter === 'pending') {
            $query->where('accepted', 0);
        } elseif ($statusFilter === 'approved') {
            $query->where('accepted', 1);
        }

        $applications = $query->orderBy('created_at', 'desc')->get();

        return view('application_list', compact('applications', 'statusFilter'));
    }
}
