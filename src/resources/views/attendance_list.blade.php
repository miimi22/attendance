@extends('layouts.app')

@section('title')
<title>勤怠一覧画面（一般ユーザー）</title>
@endsection

@section('css')
<link rel="stylesheet" href="{{ asset('css/attendance_list.css') }}" />
@endsection

@section('content')
<div class="contents">
    <h1 class="attendance-list-title">勤怠一覧</h1>
    <div class="calender-nav">
        <a href="{{ route('attendance.list', ['yearMonth' => $prevMonth]) }}" class="last-month"><img src="{{ asset('images/arrow1.png') }}" alt="←" class="arrow-logo">&nbsp前月</a>
        <div class="calender"><img src="{{ asset('images/calender.png') }}" alt="カレンダー" class="calender-logo">&nbsp{{ $displayMonth }}</div>
        <a href="{{ route('attendance.list', ['yearMonth' => $nextMonth]) }}" class="next-month">翌月&nbsp<img src="{{ asset('images/arrow2.png') }}" alt="→" class="arrow-logo"></a>
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
            {{-- データがある場合のループ --}}
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
</div>
@endsection