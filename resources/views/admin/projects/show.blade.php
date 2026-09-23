@extends('layouts.app')

@section('title', $project->key)

@section('content')
    @php
        // Old input belongs to the types form only when it came from it: a refused rename carries none, and must not uncheck every type.
        $typesSent = is_array(old('types'));
    @endphp

    <p><a href="{{ route('admin.projects.index') }}">← Проекты</a></p>
    <h1><code>{{ $project->key }}</code></h1>

    <table>
        <tbody>
        <tr><th>Адрес репозитория</th><td><code>{{ $project->repo_url }}</code></td></tr>
        <tr><th>Статус</th><td>{{ $project->is_active ? 'действует' : 'выведен из обращения' }}</td></tr>
        <tr><th>Заведён</th><td>{{ $project->created_at?->format('Y-m-d H:i') }}</td></tr>
        </tbody>
    </table>

    <h2>Реквизиты</h2>
    <form method="POST" action="{{ route('admin.projects.update', $project) }}">
        @csrf
        @method('PATCH')
        @include('admin.partials.field', ['name' => 'name', 'label' => 'Имя', 'value' => old('name', $project->name), 'required' => true])
        @include('admin.partials.field', ['name' => 'description', 'label' => 'Описание', 'type' => 'textarea', 'value' => old('description', $project->description)])
        <button type="submit">Сохранить</button>
    </form>

    <div>
        @if ($project->is_active)
            <form class="inline" method="POST" action="{{ route('admin.projects.update', $project) }}"
                  onsubmit="return confirm(@js('Вывести проект из обращения? Выдача номеров по всем его типам остановится.'))">
                @csrf
                @method('PATCH')
                <input type="hidden" name="is_active" value="0">
                <button type="submit">Вывести из обращения</button>
            </form>
        @else
            <form class="inline" method="POST" action="{{ route('admin.projects.update', $project) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="is_active" value="1">
                <button type="submit">Вернуть в обращение</button>
            </form>
        @endif
    </div>
    @include('admin.partials.error', ['field' => 'is_active'])

    <h2>Типы ключей</h2>
    @if ($keyTypes->isEmpty() && $retiredPairs->isEmpty())
        <p class="muted">Действующих типов нет: их заводят в <a href="{{ route('admin.key-types.index') }}">справочнике типов</a>.</p>
    @else
        <p class="muted">Отмеченные типы выдают номера в проекте, снятые — выключаются с сохранением счётчика. Пустое поле нового начального номера оставляет текущий.</p>

        <form id="project-key-types" method="POST" action="{{ route('admin.projects.key-types.update', $project) }}">
            @csrf
            @method('PUT')
            <table>
                <thead>
                <tr><th>Тип</th><th>Имя</th><th>Начальный номер</th><th>Новый начальный номер</th><th>Последний выданный</th><th>Следующий</th></tr>
                </thead>
                <tbody>
                @foreach ($keyTypes as $keyType)
                    @php($pair = $pairs->get($keyType->id))
                    <tr>
                        <td>
                            <label>
                                <input type="checkbox" name="types[{{ $keyType->code }}][enabled]" value="1" @checked($typesSent ? old("types.{$keyType->code}.enabled") : $pair?->is_enabled) @if ($pair?->is_enabled) data-enabled-code="{{ $keyType->code }}" @endif>
                                <code>{{ $keyType->code }}</code>
                            </label>
                            @include('admin.partials.error', ['field' => "types.{$keyType->code}.enabled"])
                        </td>
                        <td>{{ $keyType->name }}</td>
                        <td>{{ $pair?->seed_sequence ?? 0 }}</td>
                        <td>
                            <input type="number" name="types[{{ $keyType->code }}][seed_sequence]" value="{{ $typesSent ? old("types.{$keyType->code}.seed_sequence") : '' }}" min="0" placeholder="не менять">
                            @include('admin.partials.error', ['field' => "types.{$keyType->code}.seed_sequence"])
                        </td>
                        <td>{{ $pair?->last_sequence ?? 0 }}</td>
                        <td>{{ $pair?->nextSequence() ?? 1 }}</td>
                    </tr>
                @endforeach
                @foreach ($retiredPairs as $pair)
                    <tr class="muted">
                        <td><code data-enabled-code="{{ $pair->keyType->code }}">{{ $pair->keyType->code }}</code></td>
                        <td>{{ $pair->keyType->name }} — выведен из обращения, будет выключен при сохранении</td>
                        <td>{{ $pair->seed_sequence }}</td>
                        <td></td>
                        <td>{{ $pair->last_sequence }}</td>
                        <td>{{ $pair->nextSequence() }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            @include('admin.partials.error', ['field' => 'types'])
            <button type="submit">Сохранить набор типов</button>
        </form>
        @include('admin.partials.confirm-dropped-types', ['form' => 'project-key-types'])
    @endif

    <h2>Выданные номера</h2>
    <section id="issued">
        @forelse ($pairs as $pair)
            @php($identifiers = $issued[$pair->id])
            <h3>
                <code>{{ $pair->keyType->code }}</code> {{ $pair->keyType->name }}
                @if (! $pair->keyType->is_active)
                    <span class="muted">— тип выведен из обращения</span>
                @elseif (! $pair->is_enabled)
                    <span class="muted">— выключен в проекте</span>
                @endif
            </h3>
            @if ($identifiers->isEmpty())
                <p class="muted">Номеров не выдано.</p>
            @else
                <table>
                    <thead>
                    <tr><th>Номер</th><th>Идентификатор</th><th>Тема</th><th>Автор</th><th>Выдан</th></tr>
                    </thead>
                    <tbody>
                    @foreach ($identifiers as $identifier)
                        <tr>
                            <td>{{ $identifier->sequence_number }}</td>
                            <td><code>{{ $identifier->formatted_id }}</code></td>
                            <td>{{ $identifier->name }}</td>
                            <td>{{ $identifier->creator?->email ?? '—' }}</td>
                            <td>{{ $identifier->created_at?->format('Y-m-d H:i') }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                {{ $identifiers->links() }}
            @endif
        @empty
            <p class="muted">Типы в проекте ещё не включались.</p>
        @endforelse
    </section>
@endsection
