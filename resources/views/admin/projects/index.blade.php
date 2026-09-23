@extends('layouts.app')

@section('title', 'Проекты')

@section('content')
    <h1>Проекты</h1>
    <p><a class="button" href="{{ route('admin.projects.create') }}">Новый проект</a></p>

    @if ($projects->isEmpty())
        <p class="muted">Проектов пока нет.</p>
    @else
        <table>
            <thead>
            <tr><th>Ключ</th><th>Имя</th><th>Адрес репозитория</th><th>Статус</th><th>Типы: следующий номер</th></tr>
            </thead>
            <tbody>
            @foreach ($projects as $project)
                <tr>
                    <td><a href="{{ route('admin.projects.show', $project) }}"><code>{{ $project->key }}</code></a></td>
                    <td>{{ $project->name }}</td>
                    <td><code>{{ $project->repo_url }}</code></td>
                    <td>{{ $project->is_active ? 'действует' : 'выведен из обращения' }}</td>
                    <td>
                        @forelse ($project->keyTypes as $keyType)
                            <div @class(['muted' => ! $keyType->pivot->is_enabled || ! $keyType->is_active])>
                                <code>{{ $keyType->code }}</code>: {{ $keyType->pivot->nextSequence() }}
                                @unless ($keyType->pivot->is_enabled) (выключен) @endunless
                            </div>
                        @empty
                            <span class="muted">—</span>
                        @endforelse
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
@endsection
