@extends('layouts.app')

@section('title')
<title>スタッフ一覧画面（管理者）</title>
@endsection

@section('css')
<link rel="stylesheet" href="{{ asset('css/admin_staff_list.css') }}" />
@endsection

@section('content')
<div class="contents">
    <h1 class="staff-list-title">スタッフ一覧</h1>
    <table class="staff-list">
        <tr>
            <td class="name">名前</td>
            <td class="email">メールアドレス</td>
            <td class="month-attendance">月次勤怠</td>
        </tr>
        @forelse ($users as $user)
        <tr>
            <td class="name-value">{{ $user->name }}</td>
            <td class="email-value">{{ $user->email }}</td>
            <td><a href="{{ route('admin.staff.attendance.list', ['id' => $user->id]) }}" class="detail-value">詳細</a></td>
        </tr>
        @empty
        <tr>
            <td colspan="3" style="text-align: center;">スタッフ情報が見つかりません。</td>
        </tr>
        @endforelse
    </table>
</div>
@endsection