@extends('layouts.app')

@section('title', 'Вход')

@section('content')
    <h1>Вход</h1>
    <p>Сервис выдаёт номера ADR и спецификаций. Войдите корпоративным аккаунтом Google
        (<code>{{ '@'.config('getid.allowed_email_domain') }}</code>), чтобы получить токен доступа.</p>

    @error('google')
        <p class="error">{{ $message }}</p>
    @enderror

    <p>@include('partials.google-sign-in', ['label' => 'Войти с аккаунтом Google'])</p>
@endsection
