@extends('layouts.app')

@section('title', $keyType->code)

@section('content')
    <p><a href="{{ route('admin.key-types.index') }}">← Типы ключей</a></p>
    <h1><code>{{ $keyType->code }}</code></h1>
    <p>{{ $keyType->is_active ? 'Действует.' : 'Выведен из обращения.' }}</p>

    <form method="POST" action="{{ route('admin.key-types.update', $keyType) }}">
        @csrf
        @method('PATCH')
        @include('admin.partials.field', ['name' => 'name', 'label' => 'Имя', 'value' => old('name', $keyType->name), 'required' => true])
        @include('admin.partials.field', ['name' => 'format_template', 'label' => 'Шаблон — действует на номера, выданные после сохранения', 'value' => old('format_template', $keyType->format_template), 'required' => true])
        @include('admin.partials.field', ['name' => 'description', 'label' => 'Описание', 'type' => 'textarea', 'value' => old('description', $keyType->description)])
        <button type="submit">Сохранить</button>
    </form>

    <div>
        @if ($keyType->is_active)
            <form class="inline" method="POST" action="{{ route('admin.key-types.update', $keyType) }}"
                  onsubmit="return confirm(@js('Вывести тип из обращения? Выдача номеров этого типа остановится во всех проектах.'))">
                @csrf
                @method('PATCH')
                <input type="hidden" name="is_active" value="0">
                <button type="submit">Вывести из обращения</button>
            </form>
        @else
            <form class="inline" method="POST" action="{{ route('admin.key-types.update', $keyType) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="is_active" value="1">
                <button type="submit">Вернуть в обращение</button>
            </form>
        @endif
    </div>
    @include('admin.partials.error', ['field' => 'is_active'])
@endsection
