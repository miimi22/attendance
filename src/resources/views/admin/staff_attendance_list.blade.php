@extends('layouts.app')

@section('title')
<title>スタッフ別勤怠一覧画面（管理者）</title>
@endsection

@section('css')
<link rel="stylesheet" href="{{ asset('css/admin_staff_attendance_list.css') }}" />
@endsection

@section('content')
<div class="contents">
    <h1 class="staff-attendance-list-title">{{ $user->name }}さんの勤怠</h1>
    <div class="calender-nav">
        <a href="{{ route('admin.staff.attendance.list', ['id' => $user->id, 'yearMonth' => $prevMonth]) }}" class="last-month"><img src="{{ asset('images/arrow1.png') }}" alt="←" class="arrow-logo">&nbsp前月</a>
        <div class="calender"><img src="{{ asset('images/calender.png') }}" alt="カレンダー" class="calender-logo">&nbsp{{ $displayMonth }}</div>
        <a href="{{ route('admin.staff.attendance.list', ['id' => $user->id, 'yearMonth' => $nextMonth]) }}" class="next-month">翌月&nbsp<img src="{{ asset('images/arrow2.png') }}" alt="→" class="arrow-logo"></a>
    </div>
    <table class="attendance-list">
        <thead>
            <tr>
                <th class="date">日付</th>
                <th class="work-start">出勤</th>
                <th class="work-end">退勤</th>
                <th class="rest">休憩</th>
                <th class="total">合計</th>
                <th class="detail">詳細</th>
            </tr>
        </thead>
        <tbody>
            @if($attendances->isEmpty())
                <tr>
                    <td colspan="6" style="text-align: center; padding: 20px;">この月の勤怠データはありません。</td>
                </tr>
            @else
                @foreach($attendances as $attendance)
                    <tr>
                        <td class="date-value">{{ $attendance->date->format('m/d') }}({{ $attendance->date->isoFormat('ddd') }})</td>
                        <td class="work-start-value">{{ $attendance->work_start ? \Carbon\Carbon::parse($attendance->work_start)->format('H:i') : '-' }}</td>
                        <td class="work-end-value">{{ $attendance->work_end ? \Carbon\Carbon::parse($attendance->work_end)->format('H:i') : '-' }}</td>
                        <td class="rest-value">{{ $attendance->total_rest_time ? \Carbon\Carbon::parse($attendance->total_rest_time)->format('H:i') : '0:00' }}</td>
                        <td class="total-value">{{ $attendance->total_work ? \Carbon\Carbon::parse($attendance->total_work)->format('H:i') : '-' }}</td>
                        <td><a href="{{ route('attendance.detail', ['id' => $attendance->id]) }}" class="detail-value">詳細</a></td>
                    </tr>
                @endforeach
            @endif
        </tbody>
    </table>
    @php
        $currentYearMonth = isset($targetDate) ? $targetDate->format('Y-m') : \Carbon\Carbon::createFromFormat('Y/m', $displayMonth)->format('Y-m');
    @endphp
    <div class="button-wrapper" style="text-align: right;">
        <a href="{{ route('admin.staff.attendance.export', ['id' => $user->id, 'yearMonth' => $currentYearMonth]) }}" class="csv-button">CSV出力</a>
    </div>
</div>
@endsection