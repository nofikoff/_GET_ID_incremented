@extends('layouts.app')

@section('title', 'Типы ключей')

@section('content')
    <h1>Типы ключей</h1>
    <p><a class="button" href="{{ route('admin.key-types.create') }}">Новый тип</a></p>

    @if ($keyTypes->isEmpty())
        <p class="muted">Типов пока нет.</p>
    @else
        <table>
            <thead>
            <tr><th>Код</th><th>Имя</th><th>Шаблон</th><th>Статус</th></tr>
            </thead>
            <tbody>
            @foreach ($keyTypes as $keyType)
                <tr>
                    <td><a href="{{ route('admin.key-types.edit', $keyType) }}"><code>{{ $keyType->code }}</code></a></td>
                    <td>{{ $keyType->name }}</td>
                    <td><code>{{ $keyType->format_template }}</code></td>
                    <td>{{ $keyType->is_active ? 'действует' : 'выведен из обращения' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
@endsection
