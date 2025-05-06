@extends('layouts.app')

@section('title')
<title>勤怠詳細画面（管理者）</title>
@endsection

@section('css')
<link rel="stylesheet" href="{{ asset('css/admin_attendance_detail.css') }}" />
@endsection

@section('content')
<div class="contents">
    <h1 class="attendance-detail-title">勤怠詳細</h1>
    <form action="{{ route('admin.attendance.request_correction', ['id' => $attendance->id]) }}" method="POST">
        @csrf
        <table class="attendance-detail-form">
            <tr>
                <td class="name">名前</td>
                <td class="name-value">{{ $attendance->user ? $attendance->user->name : 'N/A' }}</td>
            </tr>
            <tr>
                <td class="date">日付</td>
                <td>
                    <div class="date-year">{{ $attendance->date->format('Y年') }}</div>
                    <div class="date-value">{{ $attendance->date->format('n月j日') }}</div>
                </td>
            </tr>
            <tr>
                <td class="work">出勤・退勤</td>
                <td>
                    <input type="time" name="work_start" class="work-start" value="{{ old('work_start', $attendance->work_start ? \Carbon\Carbon::parse($attendance->work_start)->format('H:i') : '') }}">
                    <div class="mark">～</div>
                    <input type="time" name="work_end" class="work-end" value="{{ old('work_end', $attendance->work_end ? \Carbon\Carbon::parse($attendance->work_end)->format('H:i') : '') }}">
                    @error('work_start')
                        <span class="input_error">
                            <p class="input_error_message">{{$errors->first('work_start')}}</p>
                        </span>
                    @enderror
                </td>
            </tr>
            @php
                $restCount = is_countable($displayRests) ? count($displayRests) : 0;
            @endphp
            @forelse ($displayRests as $index => $rest)
            <tr>
                <td class="rest">
                    休憩 {{ $loop->iteration == 1 ? '' : $loop->iteration }}
                </td>
                <td>
                    <div class="rest-row">
                        <input type="hidden" name="rest_id[{{ $index }}]" value="{{ $rest->id }}">
                        <input type="time" name="rest_start[{{ $index }}]" class="rest-start" value="{{ old('rest_start.'.$index, $rest->rest_start ? \Carbon\Carbon::parse($rest->rest_start)->format('H:i') : '') }}">
                        <div class="mark">～</div>
                        <input type="time" name="rest_end[{{ $index }}]" class="rest-end" value="{{ old('rest_end.'.$index, $rest->rest_end ? \Carbon\Carbon::parse($rest->rest_end)->format('H:i') : '') }}">
                        @error("rest_time_range.{$index}")
                            <span class="input_error">
                                <p class="input_error_message">{{ $message }}</p>
                            </span>
                        @enderror
                        @php
                            $withinWorkTimeMsgIdentifier = '休憩時間が勤務時間外です';
                        @endphp
                        @error("rest_start.{$index}")
                            @if (!Str::contains($message, $withinWorkTimeMsgIdentifier))
                                <span class="input_error">
                                    <p class="input_error_message">{{ $message }}</p>
                                </span>
                            @endif
                        @enderror
                        @error("rest_end.{$index}")
                            @if (!Str::contains($message, $withinWorkTimeMsgIdentifier))
                                <span class="input_error">
                                    <p class="input_error_message">{{ $message }}</p>
                                </span>
                            @endif
                        @enderror
                    </div>
                </td>
            </tr>
            @empty
            @endforelse
            @php
                $nextIndex = $restCount;
            @endphp
            <tr>
                <td class="rest">
                    休憩 {{ $nextIndex + 1 }}
                </td>
                <td>
                    <div class="rest-row">
                        <input type="hidden" name="rest_id[{{ $nextIndex }}]" value="">
                        <input type="time" name="rest_start[{{ $nextIndex }}]" class="rest-start"
                               value="{{ old('rest_start.'.$nextIndex) }}">
                        <div class="mark">～</div>
                        <input type="time" name="rest_end[{{ $nextIndex }}]" class="rest-end"
                               value="{{ old('rest_end.'.$nextIndex) }}">
                        @error("rest_time_range.{$nextIndex}")
                            <span class="input_error">
                                <p class="input_error_message">{{ $message }}</p>
                            </span>
                        @enderror
                        @php
                            $withinWorkTimeMsgIdentifier = '休憩時間が勤務時間外です';
                        @endphp
                        @error("rest_start.{$nextIndex}")
                            @if (!Str::contains($message, $withinWorkTimeMsgIdentifier))
                                <span class="input_error">
                                    <p class="input_error_message">{{ $message }}</p>
                                </span>
                            @endif
                        @enderror
                        @error("rest_end.{$nextIndex}")
                            @if (!Str::contains($message, $withinWorkTimeMsgIdentifier))
                                <span class="input_error">
                                    <p class="input_error_message">{{ $message }}</p>
                                </span>
                            @endif
                        @enderror
                    </div>
                </td>
            </tr>
            <tr>
                <td class="remarks">備考</td>
                <td>
                    <textarea name="remarks" id="remarks" class="remarks-value" rows="3" cols="35" placeholder="電車遅延のため">{{ old('remarks') }}</textarea>
                    @error('remarks')
                        <span class="textarea_error">
                            <p class="textarea_error_message">{{$errors->first('remarks')}}</p>
                        </span>
                    @enderror
                </td>
            </tr>
        </table>
        <button type="submit" class="correction-button">修正</button>
    </form>
</div>
@endsection