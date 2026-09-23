@extends('layouts.app')

@section('title', 'Типы ключей')

@section('content')
    <h1>Типы ключей</h1>
    <p class="muted">Типы заводятся и меняются через административный API: <code>/api/v1/admin/key-types</code>.</p>

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
                    <td><code>{{ $keyType->code }}</code></td>
                    <td>{{ $keyType->name }}</td>
                    <td><code>{{ $keyType->format_template }}</code></td>
                    <td>{{ $keyType->is_active ? 'действует' : 'выведен из обращения' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
@endsection
