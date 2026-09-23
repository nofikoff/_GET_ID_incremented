@extends('layouts.app')

@section('title', 'Токены')

@section('content')
    <h1>Токены доступа</h1>

    @if (session('plainTextToken'))
        <div class="notice">
            <p><strong>Новый токен.</strong> Скопируйте его сейчас — больше он показан не будет.</p>
            <pre>{{ session('plainTextToken') }}</pre>
            <p>Подключение к Claude Code:</p>
            <pre>claude mcp add --transport http get-id {{ url('/mcp') }} --header "Authorization: Bearer {{ session('plainTextToken') }}"</pre>
        </div>
    @endif

    <form method="POST" action="{{ route('tokens.store') }}">
        @csrf
        <p>
            <label>Имя токена, например машина, на которой он будет жить:
                <input type="text" name="name" value="{{ old('name') }}" maxlength="255" required>
            </label>
            <button type="submit">Создать</button>
        </p>
        @error('name')
            <p class="error">{{ $message }}</p>
        @enderror
    </form>

    @if ($tokens->isEmpty())
        <p class="muted">Токенов пока нет.</p>
    @else
        <table>
            <thead>
            <tr><th>Имя</th><th>Создан</th><th>Последнее обращение</th><th></th></tr>
            </thead>
            <tbody>
            @foreach ($tokens as $token)
                <tr>
                    <td>{{ $token->name }}</td>
                    <td>{{ $token->created_at?->format('Y-m-d H:i') }}</td>
                    <td>{{ $token->last_used_at?->format('Y-m-d H:i') ?? '—' }}</td>
                    <td>
                        <form method="POST" action="{{ route('tokens.destroy', $token->id) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit">Отозвать</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
@endsection
