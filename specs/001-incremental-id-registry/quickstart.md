# Quickstart: поднять get-id и получить первый номер

**Date**: 2026-09-20 | **Plan**: [plan.md](./plan.md)

Проверка «фича работает» целиком: от пустого каталога до номера, выданного через MCP. Контракты
здесь не дублируются — [rest-api.yaml](./contracts/rest-api.yaml) и
[mcp-tools.md](./contracts/mcp-tools.md).

## Предварительно

- Docker с Compose.
- OAuth-клиент Google с redirect URI `http://localhost:8080/auth/google/callback` (порт — `APP_PORT`,
  см. шаг 1).
- Аккаунт в домене `@cas.ai` — другой домен вход не пройдёт (FR-018).

## 1. Поднять окружение

```bash
cp -n .env.example .env   # APP_PORT — порт на хосте, 8080 по умолчанию
make up                   # docker compose up -d --build --wait
make init                 # composer install, key:generate
# заполнить в .env GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, ADMIN_EMAILS
make migrate              # artisan migrate --seed
```

Если 8080 занят, `make up` падает на публикации порта: `APP_PORT` меняется в `.env` до `make up`.
От него строятся `APP_URL` и redirect URI, поэтому свой порт подставляется и в шагах ниже, и в
Google Cloud Console.

Seeder заводит два типа ключей: `ADR` с шаблоном `ADR-{number:04d}` и `spec` с `{number:03d}-{name}`.

Ожидаемо: `http://localhost:8080` отвечает страницей входа.

## 2. Войти и стать администратором

Открыть `http://localhost:8080`, войти через Google. Адрес, перечисленный в `ADMIN_EMAILS`,
получает роль `admin` при первом входе (FR-021).

Проверка:

```bash
docker compose exec app php artisan tinker --execute="echo App\Models\User::first()->role->value;"
# admin
```

Вход аккаунтом другого домена должен отвергаться и не создавать пользователя — это отдельный
feature-тест, вручную проверять не нужно.

## 3. Создать токен

В личном кабинете создать токен с именем (например, `laptop`). Значение показывается один раз
(FR-019a) вместе с готовой командой `claude mcp add` для шага 7 — сохранить.

```bash
export GETID_TOKEN=<значение токена>
export GETID_URL=http://localhost:8080
```

## 4. Завести проект

Проект заводит администратор; ключ вычисляется из адреса и не задаётся руками (FR-008).

Из браузера — `/admin/projects` → «Новый проект»
([specs/002-admin-web-console/quickstart.md](../002-admin-web-console/quickstart.md) §1). Ниже —
альтернатива через curl, тем же результатом отвечает и консоль (SC-002):

```bash
curl -s -X POST "$GETID_URL/api/v1/admin/projects" \
  -H "Authorization: Bearer $GETID_TOKEN" -H "Content-Type: application/json" \
  -d '{"repo_url":"git@gitlab.cas.ai:team/backend.git","name":"Backend"}'
```

Ожидаемо: `201`, в теле `"key": "gitlab.cas.ai/team/backend"` — суффикс `.git`, часть `git@` и
SCP-форма сняты.

## 5. Включить типы в проекте

В репозитории уже лежат `ADR-0001..0042`, поэтому нумерация продолжается с 43 (FR-014a). `1` в
адресе — `id` проекта из ответа шага 4.

Из браузера — форма типов на карточке проекта, там же, §1.3 квикстарта консоли. Ниже — альтернатива
через curl:

```bash
curl -s -X PUT "$GETID_URL/api/v1/admin/projects/1/key-types" \
  -H "Authorization: Bearer $GETID_TOKEN" -H "Content-Type: application/json" \
  -d '{"types":[{"code":"ADR","seed_sequence":42},{"code":"spec","seed_sequence":0}]}'
```

Ожидаемо: `200`, у `ADR` поле `next_number: 43`.

## 6. Получить номер

```bash
curl -s -X POST "$GETID_URL/api/v1/sequence/next" \
  -H "Authorization: Bearer $GETID_TOKEN" -H "Content-Type: application/json" \
  -d '{"project_key":"gitlab.cas.ai/team/backend","type":"ADR","name":"add-oauth-auth"}'
```

Ожидаемо: `sequence_number: 43`, `formatted_id: "ADR-0043"`, `is_new: true`.

**Проверка идемпотентности** — повторить ту же команду, изменив тему на `Add OAuth Auth`:

```bash
curl -s -X POST "$GETID_URL/api/v1/sequence/next" \
  -H "Authorization: Bearer $GETID_TOKEN" -H "Content-Type: application/json" \
  -d '{"project_key":"gitlab.cas.ai/team/backend","type":"ADR","name":"Add OAuth Auth"}'
```

Ожидаемо: тот же `sequence_number: 43`, `is_new: false`, и `name` — исходная формулировка первой
выдачи `add-oauth-auth` (FR-007).

**Проверка закрытого реестра** — запросить номер для незарегистрированного проекта:

```bash
curl -s -X POST "$GETID_URL/api/v1/sequence/next" \
  -H "Authorization: Bearer $GETID_TOKEN" -H "Content-Type: application/json" \
  -d '{"project_key":"gitlab.cas.ai/team/sandbox","type":"ADR","name":"whatever"}'
```

Ожидаемо: `422`, `error.code: "project_not_registered"`, нормализованный ключ в теле (FR-010).

## 7. Подключить MCP

```bash
claude mcp add --scope user --transport http get-id "$GETID_URL/mcp" \
  --header "Authorization: Bearer $GETID_TOKEN"
claude mcp list
```

`--scope user` — сервер виден во всех проектах, а не только в текущем (умолчание `local`).
Уже запущенная сессия Claude Code не подхватывает добавленный сервер сама — `/reload-plugins`
или перезапуск сессии.

Ожидаемо: сервер `get-id` в списке, три инструмента — `resolve_project`, `next_id`,
`list_identifiers`.

Проверка сквозного сценария в сессии Claude Code, из каталога зарегистрированного репозитория:

> Заведи ADR про переход на очереди — возьми номер через get-id.

Ожидаемо: ассистент вызывает `resolve_project` с адресом из `git remote get-url origin`, затем
`next_id`, и ключ проекта нигде не вводится руками (SC-004).

## 8. Прогнать тесты

```bash
make test           # весь набор внутри контейнера
make test-race      # только tests/Concurrency
```

`make test-race` поднимает 50 параллельных процессов на одну пару «проект + тип» и проверяет, что
выдано ровно 50 различных номеров без пропусков (SC-001, принцип V конституции). Набор не
оборачивается в транзакцию и чистит таблицы усечением — иначе проверка блокировок бессмысленна.

## Развёртывание на LAMP

Целевая площадка: сервер `ruslan.pogonyalo.com`, код в `/home/develop/domains/id.x3mal.com/app`,
публичный адрес `https://id.x3mal.com` за Cloudflare. Сводка и ловушки хоста (MariaDB, `bin/php-cli`,
Composer 2) — в [README](../../README.md) §Развёртывание.

- PHP 8.3 с расширениями `pdo_mysql`, `bcmath`, `mbstring`, `curl`, `openssl`.
- DocumentRoot — `app/public` каталога домена (`virtualmin modify-web --document-dir app/public`).
- `.env`: `DB_CONNECTION=mariadb`, база и пользователь из Virtualmin, ключи Google,
  `APP_URL=https://id.x3mal.com`, `ADMIN_EMAILS`, `SESSION_SECURE_COOKIE=true`.
- `bin/php-cli artisan migrate --force`, `db:seed --force`, затем `config:cache`, `route:cache`, `view:cache`.
- Планировщик: cron пользователя `develop` раз в минуту запускает `bin/php-cli artisan schedule:run`,
  им чистится журнал обращений.
- Redirect URI в Google Cloud Console — `https://id.x3mal.com/auth/google/callback`, дословно
  совпадающий с `APP_URL`.

### Проверка Cloudflare после выкладки

Приложение стоит за проксёй, и одна вещь ломается молча — схема в сгенерированных ссылках.

```bash
php artisan tinker --execute="echo route('tokens.index');"
```

Ожидаемо: `https://id.x3mal.com/tokens`. Если `http://` — `trustProxies` не настроен, и вход через
Google не пройдёт: redirect URI не совпадёт с зарегистрированным, а ошибка Google про прокси не
скажет.
