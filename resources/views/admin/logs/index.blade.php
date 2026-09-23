@extends('layouts.app')

@section('title', 'Журнал')

@section('content')
    <h1>Журнал обращений</h1>

    <form method="GET" action="{{ route('admin.logs.index') }}">
        <p>
            <label>Сотрудник<br>
                <select name="user_id">
                    <option value="">— любой —</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected((string) request('user_id') === (string) $user->id)>
                            {{ $user->email }}@if ($user->deactivated_at) (деактивирован) @endif
                        </option>
                    @endforeach
                </select>
            </label>
            @include('admin.partials.error', ['field' => 'user_id'])
        </p>
        <p>
            <label>С<br><input type="date" name="from" value="{{ request('from') }}"></label>
            <label>По<br><input type="date" name="to" value="{{ request('to') }}"></label>
            @include('admin.partials.error', ['field' => 'to'])
        </p>
        <p>
            <label>Поверхность<br>
                <select name="surface">
                    <option value="">— любая —</option>
                    <option value="rest" @selected(request('surface') === 'rest')>REST</option>
                    <option value="mcp" @selected(request('surface') === 'mcp')>MCP</option>
                </select>
            </label>
        </p>
        <button type="submit">Показать</button>
    </form>

    <section id="logs">
        @if ($logs->isEmpty())
            <p class="muted">Записей нет.</p>
        @else
            <table>
                <thead>
                <tr><th>Дата</th><th>Сотрудник</th><th>Токен</th><th>Метод</th><th>Путь</th><th>Код</th><th>Длительность</th><th></th></tr>
                </thead>
                <tbody>
                @foreach ($logs as $log)
                    <tr>
                        <td>{{ $log->created_at?->format('Y-m-d H:i') }}</td>
                        <td>{{ $log->user?->email ?? '—' }}</td>
                        <td>{{ $log->token_name ?? '—' }}</td>
                        <td>{{ $log->method }}</td>
                        <td><code>{{ $log->endpoint }}</code></td>
                        <td>{{ $log->status_code }}</td>
                        <td>{{ $log->duration_ms }} мс</td>
                        <td>
                            <details>
                                <summary>Параметры</summary>
                                <pre>{{ json_encode($log->payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
                            </details>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            {{ $logs->links() }}
        @endif
    </section>
@endsection
