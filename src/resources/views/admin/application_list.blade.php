@extends('layouts.app')

@section('title')
<title>申請一覧画面（管理者）</title>
@endsection

@section('css')
<link rel="stylesheet" href="{{ asset('css/admin_application_list.css') }}" />
@endsection

@section('content')
<div class="contents">
    <h1 class="application-list-title">申請一覧</h1>
    <div class="tab">
        <a href="{{ route('application.list', ['status' => 'pending']) }}" class="pending-approval {{ $status === 'pending' ? 'active' : '' }}">承認待ち</a>
        <a href="{{ route('application.list', ['status' => 'approved']) }}" class="approved {{ $status === 'approved' ? 'active' : '' }}">承認済み</a>
    </div>
    <div class="border"></div>
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
            @forelse ($applications as $application)
            <tr>
                <td class="situation-value">
                    @if($application->accepted === 0)
                        <span>承認待ち</span>
                    @elseif($application->accepted === 1)
                        <span>承認済み</span>
                    @else
                        不明 ({{ $application->accepted }})
                    @endif
                </td>
                <td class="name-value">{{ optional(optional($application->attendance)->user)->name ?? '取得エラー' }}</td>
                <td class="subject-date-value">{{ $application->date->format('Y/m/d') }}</td>
                <td class="application-reason-value">{{ Str::limit($application->remarks, 50) }}</td>
                <td class="application-date-value">{{ $application->created_at->format('Y/m/d') }}</td>
                <td>
                    <a href="{{ url('/stamp_correction_request/approve/' . $application->id) }}" class="detail-value">詳細</a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" style="text-align: center;">対象の申請はありません。</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection