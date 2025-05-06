@extends('layouts.app')

@section('title')
<title>申請一覧画面（一般ユーザー）</title>
@endsection

@section('css')
<link rel="stylesheet" href="{{ asset('css/application_list.css') }}" />
@endsection

@section('content')
<div class="contents">
    <h1 class="application-list-title">申請一覧</h1>
    <div class="tab">
        <a href="{{ route('application.list', ['status' => 'pending']) }}" class="pending-approval {{ $statusFilter === 'pending' ? 'active' : '' }}">承認待ち</a>
        <a href="{{ route('application.list', ['status' => 'approved']) }}" class="approved {{ $statusFilter === 'approved' ? 'active' : '' }}">承認済み</a>
    </div>
    <div class="border"></div>
    @if($applications->count() > 0)
        <table class="application-list">
            <thead>
                <tr>
                    <th class="situation">状態</th>
                    <th class="name">名前</th>
                    <th class="subject-date">対象日時</th>
                    <th class="application-reason">申請理由</th>
                    <th class="application-date">申請日時</th>
                    <th class="detail">詳細</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($applications as $application)
                    <tr>
                        <td class="situation-value">{{ $application->status_text }}</td>
                        <td class="name-value">{{ optional(optional($application->attendance)->user)->name }}</td>
                        <td class="subject-date-value">{{ $application->formatted_subject_date }}</td>
                        <td class="application-reason-value">{{ $application->remarks }}</td>
                        <td class="application-date-value">{{ $application->formatted_application_date }}</td>
                        <td>
                            @if($application->attendance_id)
                                <a href="{{ route('attendance.detail', ['id' => $application->attendance_id]) }}" class="detail-value">詳細</a>
                            @else
                                詳細なし
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody> 
        </table>
    @else
        <p style="text-align: center; margin-top: 20px;">
            @if ($statusFilter === 'pending')
                承認待ちの申請はありません。
            @elseif ($statusFilter === 'approved')
                承認済みの申請はありません。
            @else
                表示する申請はありません。
            @endif
        </p>
    @endif
</div>
@endsection