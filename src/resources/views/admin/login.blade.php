@extends('layouts.app')

@section('title')
<title>ログイン画面（管理者）</title>
@endsection

@section('css')
<link rel="stylesheet" href="{{ asset('css/admin_login.css') }}" />
@endsection

@section('content')
<div class="contents">
    <h1>管理者ログイン</h1>
    <form action="/admin/login" method="post">   
    @csrf
        <label for="email" class="label">メールアドレス</label>
        <input id="email" type="email" name="email" class="text" value="{{ old('email') }}">
        @error('email')
            <span class="input_error">
                <p class="input_error_message">{{$errors->first('email')}}</p>
            </span>
        @enderror
        <label for="password" class="label">パスワード</label>
        <input id="password" type="password" name="password" class="text">
        @error('password')
            <span class="input_error">
                <p class="input_error_message">{{$errors->first('password')}}</p>
            </span>
        @enderror
        <div class="button-content">
            <button class="button-login" type="submit">管理者ログインする</button>
        </div>
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