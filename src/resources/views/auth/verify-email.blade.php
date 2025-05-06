@extends('layouts.app')

@section('title')
<title>メール認証誘導画面</title>
@endsection

@section('css')
<link rel="stylesheet" href="{{ asset('css/verify-email.css') }}" />
@endsection

@section('content')
<div class="contents">
    <p class="message1">登録していただいたメールアドレスに認証メールを送付しました。</p>
    <p class="message2">メール認証を完了してください。</p>
    @if (app()->environment('local'))
        <a href="{{ config('app.mailhog_url', 'http://localhost:8025') }}" target="_blank" class="verification">
            認証はこちらから
        </a>
    @else
    @endif
    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <button type="submit" class="verification-mail-button">認証メールを再送する</button>
    </form>
</div>
@endsection

@section('style')
<style>
    body {
        background-color: white !important;
    }
</style>
@endsection