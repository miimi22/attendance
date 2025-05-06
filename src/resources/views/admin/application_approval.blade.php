@extends('layouts.app')

@section('title')
<title>修正申請承認画面（管理者）</title>
@endsection

@section('css')
<link rel="stylesheet" href="{{ asset('css/admin_application_approval.css') }}" />
@endsection

@section('content')
<div class="contents">
    @if(isset($application))
        <h1 class="attendance-detail-title">勤怠詳細</h1>
        <table class="attendance-detail-form">
            <tr>
                <td class="name">名前</td>
                <td class="name-value">{{ $displayData['name'] }}</td>
            </tr>
            <tr>
                <td class="date">日付</td>
                <td>
                    <div class="date-year">{{ $displayData['date']->format('Y年') }}</div>
                    <div class="date-value">{{ $displayData['date']->format('n月j日') }}</div>
                </td>
            </tr>
            <tr>
                <td class="work">出勤・退勤</td>
                <td>
                    <div class="work-start">{{ $displayData['work_start'] ? \Carbon\Carbon::parse($displayData['work_start'])->format('H:i') : '--:--' }}</div>
                    <div class="mark">～</div>
                    <div class="work-end">{{ $displayData['work_end'] ? \Carbon\Carbon::parse($displayData['work_end'])->format('H:i') : '--:--' }}</div>
                </td>
            </tr>
            @php
                $correctedRests = $application->corrected_rests ?? null;
                $originalRests = optional($application->attendance)->rests ?? null;

                $hasCorrectedRests = is_array($correctedRests) && !empty($correctedRests);
                $hasOriginalRests = $originalRests instanceof \Illuminate\Support\Collection && $originalRests->isNotEmpty();
            @endphp
            @if($hasCorrectedRests)
                @foreach ($correctedRests as $index => $rest)
                    <tr>
                        <td class="rest">
                            休憩{{ $index > 0 ? $index + 1 : '' }}
                        </td>
                        <td>
                            <div class="rest-pair">
                                <div class="rest-start">
                                    {{ !empty($rest['start']) ? \Carbon\Carbon::parse($rest['start'])->format('H:i') : '--:--' }}
                                </div>
                                <div class="mark">～</div>
                                <div class="rest-end">
                                    {{ !empty($rest['end']) ? \Carbon\Carbon::parse($rest['end'])->format('H:i') : '--:--' }}
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            @elseif($hasOriginalRests)
                @foreach ($originalRests as $index => $rest)
                    <tr>
                        <td class="rest">
                            休憩{{ $index > 0 ? $index + 1 : '' }}
                        </td>
                        <td>
                            <div class="rest-pair">
                                <div class="rest-start">
                                    {{ !empty($rest->rest_start) ? \Carbon\Carbon::parse($rest->rest_start)->format('H:i') : '--:--' }}
                                </div>
                                <div class="mark">～</div>
                                <div class="rest-end">
                                    {{ !empty($rest->rest_end) ? \Carbon\Carbon::parse($rest->rest_end)->format('H:i') : '--:--' }}
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            @else
                <tr>
                    <td class="rest">休憩</td>
                    <td>
                        <div class="no-rest-data">休憩データなし</div>
                    </td>
                </tr>
            @endif
            <tr>
                <td class="remarks">備考</td>
                <td class="remarks-value">{{ $application->remarks }}</td>
            </tr>
        </table>
        <div class="container">
            @if ($application->accepted === 0)
                <form method="POST" action="{{ route('admin.application.approve.legacy', ['attendance_correct_request' => $application->id]) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="approval-button">承認</button>
                </form>
            @elseif ($application->accepted === 1)
                <div class="approved">承認済み</div>
            @endif
        </div>
    @else
        <p>申請データが見つかりません。</p>
    @endif
</div>
@endsection