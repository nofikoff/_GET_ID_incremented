@extends('layouts.app')

@section('title', 'Помощь')

@section('content')
    <h1>Помощь</h1>

    <h2>Подключение Claude Code к get-id</h2>
    <ol>
        <li>
            Создайте токен в <a href="{{ route('tokens.index') }}">кабинете токенов</a> —
            значение показывается один раз, сразу после создания, и больше не хранится нигде.
        </li>
        <li>
            Выполните команду, подставив свой токен вместо <code>&lt;токен&gt;</code>:
            @include('partials.mcp-connect-command', ['token' => '<токен>'])
        </li>
        <li>
            Из уже запущенной сессии Claude Code slash-команды, добавляющей сервер, нет —
            <code>/mcp</code> только показывает список и авторизует. Выполните ту же команду с
            префиксом <code>!</code>.
        </li>
        <li>
            Запущенная сессия не подхватывает новый сервер сама: выполните
            <code>/reload-plugins</code> или перезапустите сессию.
        </li>
        <li>
            Проверьте подключение: <code>claude mcp list</code> должен показать <code>get-id</code>.
        </li>
    </ol>

    <h2>Инструменты сервера</h2>
    <ul>
        <li>
            <code>resolve_project</code> — определить ключ проекта get-id по адресу origin
            git-репозитория и узнать, зарегистрирован ли он.
        </li>
        <li>
            <code>next_id</code> — получить следующий свободный номер для документа в проекте
            get-id.
        </li>
        <li>
            <code>list_identifiers</code> — показать уже выданные номера в проекте по типу
            ключа, от новых к старым.
        </li>
    </ul>
@endsection
