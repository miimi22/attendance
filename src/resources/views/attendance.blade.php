@extends('layouts.app')

@section('title')
<title>勤怠登録画面（一般ユーザー）</title>
@endsection

@section('css')
<link rel="stylesheet" href="{{ asset('css/attendance.css') }}" />
@endsection

@section('content')
<div class="contents">
    <div class="situation">
        @php
            use App\Models\User;
        @endphp
        @if($status == User::STATUS_OFF_WORK)
            勤務外
        @elseif($status == User::STATUS_ON_WORK)
            出勤中
        @elseif($status == User::STATUS_ON_REST)
            休憩中
        @elseif($status == User::STATUS_LEFT_WORK)
            退勤済
        @endif
    </div>
    <div class="datetime-info">
        <div class="date">
            {{ $currentDate }} ({{ $currentDayOfWeek }})
        </div>
        <div class="time">{{ $currentTime }}</div>
    </div>
    <div class="attendance-buttons">
        @if($status == User::STATUS_OFF_WORK)
            <form method="POST" action="{{ route('attendance.workstart') }}">
                @csrf
                <button type="submit" class="attendance-button work-start-button">
                    出勤
                </button>
            </form>
        @endif
        <div class="work-end">
            @if($status == User::STATUS_ON_WORK)
                <form method="POST" action="{{ route('attendance.workend') }}">
                    @csrf
                    <button type="submit" class="attendance-button work-end-button">退勤</button>
                </form>
            @endif
            @if($status == User::STATUS_ON_WORK)
                <form method="POST" action="{{ route('attendance.reststart') }}">
                    @csrf
                    <button type="submit" class="attendance-button rest-start-button">休憩入</button>
                </form>
            @endif
        </div>
        @if($status == User::STATUS_ON_REST)
            <form method="POST" action="{{ route('attendance.restend') }}">
                @csrf
                <button type="submit" class="attendance-button rest-end-button">休憩戻</button>
            </form>
         @endif
    </div>
    @if($status == User::STATUS_LEFT_WORK)
        <p class="good-work-message">お疲れ様でした。</p>
    @endif
</div>
@endsection