@extends('layouts.app')

@section('title', 'Новый тип ключа')

@section('content')
    <p><a href="{{ route('admin.key-types.index') }}">← Типы ключей</a></p>
    <h1>Новый тип ключа</h1>
    <p class="muted">Код после заведения не меняется.</p>

    <form method="POST" action="{{ route('admin.key-types.store') }}">
        @csrf
        @include('admin.partials.field', ['name' => 'code', 'label' => 'Код, например RFC', 'value' => old('code'), 'required' => true])
        @include('admin.partials.field', ['name' => 'name', 'label' => 'Имя', 'value' => old('name'), 'required' => true])
        @include('admin.partials.field', ['name' => 'description', 'label' => 'Описание', 'type' => 'textarea', 'value' => old('description')])
        <button type="submit">Завести</button>
    </form>
@endsection
