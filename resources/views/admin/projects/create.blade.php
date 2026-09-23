@extends('layouts.app')

@section('title', 'Новый проект')

@section('content')
    <p><a href="{{ route('admin.projects.index') }}">← Проекты</a></p>
    <h1>Новый проект</h1>
    <p class="muted">Ключ проекта выводится из адреса репозитория и после заведения не меняется.</p>

    <form method="POST" action="{{ route('admin.projects.store') }}">
        @csrf
        @include('admin.partials.field', ['name' => 'repo_url', 'label' => 'Адрес репозитория, например git@gitlab.cas.ai:team/backend.git', 'value' => old('repo_url'), 'required' => true])
        @include('admin.partials.field', ['name' => 'name', 'label' => 'Имя', 'value' => old('name'), 'required' => true])
        @include('admin.partials.field', ['name' => 'description', 'label' => 'Описание', 'type' => 'textarea', 'value' => old('description')])
        <button type="submit">Завести</button>
    </form>
@endsection
