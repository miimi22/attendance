@extends('layouts.app')

@section('title')
<title>勤怠一覧画面（管理者）</title>
@endsection

@section('css')
<link rel="stylesheet" href="{{ asset('css/admin_attendance_list.css') }}" />
@endsection

@section('content')
<div class="contents">
    <h1 class="attendance-list-title">{{ $displayDate }}の勤怠</h1>
    <div class="calender-nav">
        <a href="{{ route('admin.attendance.list', ['date' => $previousDate]) }}" class="last-day"><img src="{{ asset('images/arrow1.png') }}" alt="←" class="arrow-logo">&nbsp;前日</a>
        <div class="calender"><img src="{{ asset('images/calender.png') }}" alt="カレンダー" class="calender-logo">&nbsp;{{ \Carbon\Carbon::parse($currentDate)->format('Y/m/d') }}</div>
        <a href="{{ route('admin.attendance.list', ['date' => $nextDate]) }}" class="next-day">翌日&nbsp;<img src="{{ asset('images/arrow2.png') }}" alt="→" class="arrow-logo"></a>
    </div>
    <table class="attendance-list">
        <thead>
            <tr>
                <th class="name">名前</th>
                <th class="work-start">出勤</th>
                <th class="work-end">退勤</th>
                <th class="rest">休憩</th>
                <th class="total">合計</th>
                <th class="detail">詳細</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($attendances as $attendance)
            <tr>
                <td class="name-value">{{ $attendance->user ? $attendance->user->name : 'ユーザー不明' }}</td>
                <td class="work-start-value">{{ $attendance->work_start ? \Carbon\Carbon::parse($attendance->work_start)->format('H:i') : '' }}</td>
                <td class="work-end-value">{{ $attendance->work_end ? \Carbon\Carbon::parse($attendance->work_end)->format('H:i') : '' }}</td>
                <td class="rest-value">{{ $attendance->total_rest_time ?? '0:00:00' }}</td>
                <td class="total-value">{{ $attendance->actual_work_time ?? '' }}</td>
                <td><a href="{{ route('attendance.detail', ['id' => $attendance->id]) }}" class="detail-value">詳細</a></td>
            </tr>
            @empty
            <tr>
                <td colspan="6" style="text-align: center;">この日付の勤怠データはありません。</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection