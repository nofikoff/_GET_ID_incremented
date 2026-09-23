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

    <h2>Что написать в CLAUDE.md проекта</h2>
    <p>
        Без этой инструкции ассистент возьмёт номер по привычке — листингом каталога, и сервис не спасёт от
        гонки. Скопируйте блок в корневой <code>CLAUDE.md</code> репозитория, на английском:
    </p>
    <pre>## Document numbers (ADR, specs)

ADR and spec numbers are issued by the get-id service, never derived from listing `docs/adr/` or `specs/`: two sessions listing the same directory take the same number.

- Before creating an ADR or a spec package, call the `get-id` MCP tools: `resolve_project` with the output of `git remote get-url origin`, then `next_id` with the returned `project_key`, `type` (`ADR` or `spec`) and `name` — the document's slug in ASCII kebab-case.
- The service issues only the number (`sequence_number`), never a document name: the repository builds the name, and you never count the number yourself. Zero-pad it to three digits: 43 → `docs/adr/adr-043-&lt;slug&gt;.md`, `specs/043-&lt;slug&gt;/`; a number of 1000 or more is written in full.
- Spec Kit: pass the number explicitly — `create-new-feature.sh --number &lt;sequence_number&gt; --short-name &lt;slug&gt;`. When that prefix already exists the script takes another number and only prints a warning to stderr — if the created directory does not start with the issued three-digit number, stop and tell the user.
- Repeating `next_id` with the same slug returns the same number (`is_new: false`), so it is safe after a retry or a crash. Never change the slug to get a fresh number.
- A number taken by mistake is released only by a get-id admin: stop and tell the user to ask one to delete it. Only the last issued number of the type in the project can be deleted — once a later one is issued, the mistaken number stays a gap for good, so ask right away. Do not reuse it for another document and do not work around it with a new slug.
- `project_not_registered` or `type_not_enabled`: stop and tell the user to ask an admin to register the project at {{ route('admin.projects.index') }}. Do not fall back to listing the directory.
- One-time setup per machine: sign in at {{ url('/') }} with the corporate Google account, create a token on {{ route('tokens.index') }}, run the `claude mcp add --scope user …` command shown there, then `/reload-plugins`.</pre>

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
