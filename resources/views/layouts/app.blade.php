<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · get-id</title>
    {{-- Inline on purpose: the LAMP host serves no Vite build (quickstart.md, deployment). --}}
    <style>
        :root { color-scheme: light dark; --muted: #6b7280; --line: #d1d5db; --accent: #2563eb; --bad: #b91c1c; }
        * { box-sizing: border-box; }
        body { margin: 0; font: 15px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; }
        header { display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; padding: .75rem 1.5rem; border-bottom: 1px solid var(--line); }
        header nav { display: flex; gap: 1rem; flex: 1; }
        header form { display: flex; gap: .75rem; align-items: center; margin: 0; color: var(--muted); }
        main { max-width: 64rem; margin: 0 auto; padding: 1.5rem; }
        a { color: var(--accent); }
        table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
        th, td { text-align: left; padding: .4rem .6rem; border-bottom: 1px solid var(--line); vertical-align: top; }
        th { color: var(--muted); font-weight: 500; }
        code, pre { font: 13px/1.4 ui-monospace, SFMono-Regular, Menlo, monospace; }
        pre { white-space: pre-wrap; word-break: break-all; padding: .75rem; border: 1px solid var(--line); border-radius: 6px; }
        button, .button { font: inherit; padding: .35rem .9rem; border: 1px solid var(--line); border-radius: 6px; background: none; color: inherit; cursor: pointer; text-decoration: none; display: inline-block; }
        input[type=text], textarea { font: inherit; padding: .35rem .5rem; border: 1px solid var(--line); border-radius: 6px; min-width: 16rem; }
        input[type=number] { font: inherit; padding: .2rem .4rem; border: 1px solid var(--line); border-radius: 6px; width: 8rem; }
        textarea { width: 100%; max-width: 40rem; }
        form.inline { display: inline; }
        .pagination { display: flex; flex-wrap: wrap; gap: .25rem; list-style: none; padding: 0; margin: 0 0 1rem; }
        .pagination li > * { display: inline-block; min-width: 2rem; padding: .15rem .5rem; border: 1px solid var(--line); border-radius: 6px; text-align: center; }
        .pagination .active > *, .pagination .disabled > * { color: var(--muted); }
        .notice { padding: .75rem 1rem; border: 1px solid var(--accent); border-radius: 6px; }
        .error { color: var(--bad); }
        .muted { color: var(--muted); }
    </style>
</head>
<body>
<header>
    <strong>get-id</strong>
    @auth
        <nav>
            <a href="{{ route('tokens.index') }}">Токены</a>
            @can('administer')
                <a href="{{ route('admin.projects.index') }}">Проекты</a>
                <a href="{{ route('admin.key-types.index') }}">Типы ключей</a>
                <a href="{{ route('admin.logs.index') }}">Журнал</a>
            @endcan
        </nav>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <span>{{ auth()->user()->email }}</span>
            <button type="submit">Выйти</button>
        </form>
    @endauth
</header>
<main>
    @if (session('status'))
        <p class="notice">{{ session('status') }}</p>
    @endif

    @yield('content')
</main>
</body>
</html>
