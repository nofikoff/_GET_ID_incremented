---

description: "Task list for incremental id registry"
---

# Tasks: Реестр инкрементальных идентификаторов

> **Reviewed:** 2026-09-20 by /plan
> **Fixed:** 2026-09-20 by /plan-fix

**Input**: Design documents from `/specs/001-incremental-id-registry/`

**Prerequisites**: [plan.md](./plan.md), [spec.md](./spec.md), [research.md](./research.md),
[data-model.md](./data-model.md), [contracts/](./contracts/)

**Tests**: включены и не опциональны. Конституция репозитория требует параллельного теста на гонку
(принцип V) и прогона набора в Docker до объявления готовности (принцип VII); идемпотентность
выдачи закрывается тестом, а не рассуждением (принцип I).

**Organization**: задачи сгруппированы по пользовательским историям. Каждая история — законченный
прирост, который можно проверить отдельно.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: можно выполнять параллельно (разные файлы, нет зависимости от незакрытых задач)
- **[Story]**: к какой истории относится задача (US1…US5)
- Путь к файлу указан в описании

## Path Conventions

Стандартная раскладка Laravel в корне репозитория: `app/`, `database/`, `routes/`, `resources/`,
`tests/`. Доменный слой — `app/Domain/`. Раскладка целиком в [plan.md](./plan.md) §Project Structure.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: пустой каталог превращается в работающее приложение Laravel под Docker

### Step 1.1: Laravel, Docker и инструменты

<!-- plan-meta:
allowed_paths:
  - "app/**"
  - "bootstrap/**"
  - "config/**"
  - "database/**"
  - "routes/**"
  - "resources/**"
  - "tests/**"
  - "docker/**"
  - "composer.json"
  - "docker-compose.yml"
  - "Makefile"
  - "phpunit.xml"
  - ".env.example"
  - ".dockerignore"
  - ".gitignore"
gate_commands:
  lint: "docker compose exec -T app ./vendor/bin/pint --test"
  type: "docker compose exec -T app ./vendor/bin/phpstan analyse --no-progress"
  test_quick: "docker compose exec -T app php artisan test --testsuite=Unit"
  test_full: "make test"
tier: strong
-->

- [x] T001 Создать приложение Laravel 13 поверх существующего репозитория, не затирая уже лежащие файлы: `composer create-project laravel/laravel:^13.0 tmp-app --no-install`, затем `rsync -a --ignore-existing tmp-app/ ./ && rm -rf tmp-app && composer install`. Глоб `.*` не применять — он раскрывается в `.` и `..`. Skeleton несёт собственные `.gitignore` и `README.md`; `--ignore-existing` оставит наши, поэтому правила Laravel (`/vendor`, `/public/build`, `/storage/*.key`) в `.gitignore` смержить вручную
- [x] T002 Зафиксировать PHP 8.3 в `composer.json` (`"php": "^8.3"`) и сверить, что `composer.lock` не тянет пакеты с более низкой границей
- [x] T003 [P] Написать `docker/php/Dockerfile` (php-fpm 8.3 с `pdo_mysql`, `bcmath`, `mbstring`, `intl`), `docker/nginx/default.conf` с корнем в `public/`
- [x] T004 [P] Написать `docker-compose.yml`: сервисы `app`, `nginx` (порт 8080), `mysql` 8.0 с томом и healthcheck; `app` зависит от `mysql` через `depends_on.condition: service_healthy`, иначе `make migrate` сразу после `make up` падает на первом запуске
- [x] T004a [P] Написать `.dockerignore`: `vendor/`, `node_modules/`, `.git/`, `storage/logs/`, `.env` — без него содержимое этих каталогов уезжает в образ
- [x] T005 [P] Написать `Makefile` с целями `up`, `down`, `migrate`, `fresh`, `test` (все четыре suite), `test-race` (`php artisan test --testsuite=Concurrency`), `shell` — все через `docker compose exec app`
- [x] T006 Установить зависимости: `composer require laravel/sanctum laravel/socialite laravel/mcp` и опубликовать конфиги Sanctum
- [x] T007 [P] Установить инструменты качества: `composer require --dev larastan/larastan laravel/pint pestphp/pest`, настроить `phpstan.neon` на уровень 6 и `pint.json`
- [x] T008 [P] Заполнить `.env.example` ключами `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`, `ADMIN_EMAILS`, `ALLOWED_EMAIL_DOMAIN=cas.ai`, `API_LOG_RETENTION_DAYS=90`
- [x] T104 Перевести тесты на MySQL в `phpunit.xml`: `DB_CONNECTION=mysql`, `DB_DATABASE=getid_test` вместо `sqlite`/`:memory:` из skeleton; базу `getid_test` создавать init-скриптом контейнера `mysql` (`docker/mysql/init/01-test-db.sql`). Переменные `DB_*` объявлять с `force="true"`: без него `<env>` не перекрывает значение, уже заданное окружением контейнера, и тесты молча уходят в рабочую базу — а набор `Concurrency` её усекает. Добавить testsuites `Mcp` (`tests/Mcp`) и `Concurrency` (`tests/Concurrency`) к `Unit` и `Feature`. Без этого `lockForUpdate()` не проверяется вообще — SQLite не поддерживает `SELECT ... FOR UPDATE`, — 50 процессов теста гонки получают 50 разных пустых баз в памяти, а `make test` молча пропускает `tests/Mcp` и `tests/Concurrency` (принципы V и VII)
- [x] T009 Добавить в `Makefile` цель `init` (`cp -n .env.example .env`, `composer install`, `php artisan key:generate`) и проверить, что `make up && make init && make migrate` поднимает окружение с нуля и стандартные миграции Laravel проходят

**Checkpoint**: приложение отвечает на `http://localhost:8080`, тесты запускаются в контейнере

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: схема, модели и три value object, на которые опираются все истории

**⚠️ CRITICAL**: ни одна история не начинается, пока эта фаза не закрыта

### Step 2.1: Схема данных

<!-- plan-meta:
allowed_paths:
  - "app/**"
  - "bootstrap/**"
  - "config/**"
  - "database/**"
  - "routes/**"
  - "resources/**"
  - "tests/**"
gate_commands:
  lint: "docker compose exec -T app ./vendor/bin/pint --test"
  type: "docker compose exec -T app ./vendor/bin/phpstan analyse --no-progress"
  test_quick: "docker compose exec -T app php artisan migrate:fresh --seed"
  test_full: "make test"
tier: strong
-->


- [x] T010 Отредактировать исходную миграцию `database/migrations/0001_01_01_000000_create_users_table.php`: добавить `google_id`, `avatar_url`, `role` enum(`member`,`admin`) default `member`, `deactivated_at`; убрать `password` и `email_verified_at`. Приложение ещё не развёрнуто, поэтому отдельная миграция «поправить то, что сами же создали строкой выше» осталась бы в дереве навсегда
- [x] T011 [P] Миграция `database/migrations/*_create_projects_table.php` по [data-model.md](./data-model.md) §projects, включая UNIQUE по `key` с учётом лимита длины индекса InnoDB
- [x] T012 [P] Миграция `database/migrations/*_create_key_types_table.php` по §key_types
- [x] T013 Миграция `database/migrations/*_create_project_key_type_table.php` по §project_key_type: `seed_sequence`, `last_sequence`, `is_enabled`, UNIQUE `(project_id, key_type_id)`
- [x] T014 Миграция `database/migrations/*_create_identifiers_table.php` по §identifiers: оба UNIQUE-индекса, индекс для перечня по убыванию; `down()` дропает таблицу, только если она пуста, а на непустой бросает исключение (data-model.md §identifiers, конституция §Порядок работы)
- [x] T015 [P] Миграция `database/migrations/*_create_api_logs_table.php` по §api_logs с индексом по `created_at`

### Step 2.2: Модели

<!-- plan-meta:
allowed_paths:
  - "app/**"
  - "bootstrap/**"
  - "config/**"
  - "database/**"
  - "routes/**"
  - "resources/**"
  - "tests/**"
gate_commands:
  lint: "docker compose exec -T app ./vendor/bin/pint --test"
  type: "docker compose exec -T app ./vendor/bin/phpstan analyse --no-progress"
  test_quick: "docker compose exec -T app php artisan test --testsuite=Unit"
  test_full: "make test"
tier: standard
-->


- [x] T016 [P] Модель `app/Models/Project.php`: связь `keyTypes()` через `project_key_type` с `withPivot`, scope `active()`
- [x] T017 [P] Модель `app/Models/KeyType.php` со scope `active()`
- [x] T018 [P] Модель `app/Models/ProjectKeyType.php` (pivot как полноценная модель — она несёт счётчик)
- [x] T019 [P] Модель `app/Models/Identifier.php` без `update`/`delete` в публичном интерфейсе
- [x] T020 [P] Модель `app/Models/ApiLog.php`
- [x] T021 Дополнить `app/Models/User.php`: `HasApiTokens`, каст `role` в enum `app/Enums/UserRole.php`, метод `isAdmin()`, scope `active()`

### Step 2.3: Value objects

<!-- plan-meta:
allowed_paths:
  - "app/**"
  - "bootstrap/**"
  - "config/**"
  - "database/**"
  - "routes/**"
  - "resources/**"
  - "tests/**"
gate_commands:
  lint: "docker compose exec -T app ./vendor/bin/pint --test"
  type: "docker compose exec -T app ./vendor/bin/phpstan analyse --no-progress"
  test_quick: "docker compose exec -T app php artisan test --testsuite=Unit"
  test_full: "make test"
tier: strong
-->


- [x] T022 [P] Тест `tests/Unit/ProjectKeyTest.php`: таблица примеров SSH/HTTPS/порт/`.git`/регистр → один ключ, уже нормализованный ключ на входе → тот же ключ (идемпотентность), плюс неразбираемые строки (FR-008, FR-008a, research.md §R5)
- [x] T023 [P] Тест `tests/Unit/DocumentNameTest.php`: регистр, пробелы, подчёркивания, точки и прочая пунктуация, повторы разделителей, кириллица без транслитерации, одна и та же буква в составной и разложенной форме Unicode, пустой результат (FR-007, FR-007a)
- [x] T024 [P] Тест `tests/Unit/IdentifierFormatTest.php`: `{number}`, `{number:04d}`, `{name}`, неизвестный плейсхолдер, отсутствие номера, номер шире ширины шаблона (FR-013a, Edge Cases)
- [x] T025 [P] Реализовать `app/Domain/Project/ProjectKey.php` — разбор без `parse_url` для SCP-формы
- [x] T026 [P] Реализовать `app/Domain/KeyType/DocumentName.php` — хранит исходную строку и slug
- [x] T027 [P] Реализовать `app/Domain/KeyType/IdentifierFormat.php` — разбор шаблона и применение, без `sprintf` от чужой строки

### Step 2.4: Инфраструктура запроса

<!-- plan-meta:
allowed_paths:
  - "app/**"
  - "bootstrap/**"
  - "config/**"
  - "database/**"
  - "routes/**"
  - "resources/**"
  - "tests/**"
gate_commands:
  lint: "docker compose exec -T app ./vendor/bin/pint --test"
  type: "docker compose exec -T app ./vendor/bin/phpstan analyse --no-progress"
  test_quick: "docker compose exec -T app php artisan test --testsuite=Feature"
  test_full: "make test"
tier: strong
-->


- [x] T028 Определить в `app/Providers/AppServiceProvider.php` именованный `RateLimiter::for('getid')`, ключующийся по `$request->user()?->currentAccessToken()?->id` с порогом 60 в минуту. Стандартный `throttle:60,1` ключуется по идентификатору пользователя, а FR-020a требует счёта **по токену** — у пользователя их несколько; вдобавок два независимых лимита на группах `api` и `/mcp` дали бы суммарно 120 запросов в минуту вместо 60
- [x] T103 Настроить доверенные прокси в `bootstrap/app.php`: `$middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_PROTO)`. Доверяется только схема — ровно то, что нужно, чтобы за Cloudflare `url()` отдавал `https://` и redirect URI совпадал с зарегистрированным в Google; без этого вход ломается с ошибкой, не упоминающей прокси. `X-Forwarded-For` и `X-Forwarded-Host` в доверенные не входят: адрес клиента нигде не используется, а подмена host через запрос в обход Cloudflare исключается на корню
- [x] T028a Настроить `bootstrap/app.php` целиком за один заход: группа `api` с `auth:sanctum` и `throttle:getid` (маршрут MCP живёт в `routes/api.php` и наследует её — отдельная группа для MCP не заводится, она не применялась бы ни к одному маршруту; `apiPrefix: ''`, поэтому REST объявляет префикс `api/v1` сам), и регистрация `LogApiRequest` (здесь создаётся сквозная заглушка — незарезолвленный класс в активной группе ронял бы каждый запрос API; T084 её наполняет). Вместе с T103 это единственное место, где правится `bootstrap/app.php`, и обе задачи лежат в одном шаге: две разные фазы, пишущие этот файл, при исполнении бандлами конфликтуют
- [x] T029 [P] Создать иерархию доменных исключений в `app/Domain/Sequence/Exceptions/`: `UnknownProject`, `InactiveProject`, `TypeNotEnabled`, `InactiveKeyType`, `UnparsableOrigin`, `EmptyDocumentName` — каждое несёт код из `DomainError.error.code` контракта
- [x] T030 Отрисовать доменные исключения в JSON формы `DomainError` (contracts/rest-api.yaml) через `withExceptions()->render()` со статусом 422. Отдельно привести к той же форме исключения фреймворка, которые контракт тоже описывает как `DomainError`: `AuthenticationException` → 401 `unauthenticated`, `AccessDeniedHttpException` → 403 `forbidden`, `NotFoundHttpException` → 404 `not_found`, `ThrottleRequestsException` → 429 `rate_limited`
- [x] T031 [P] Фабрики `database/factories/` для `Project`, `KeyType`, `ProjectKeyType`, `Identifier`
- [x] T032 [P] Seeder `database/seeders/KeyTypeSeeder.php`: `ADR` → `ADR-{number:04d}`, `spec` → `{number:03d}-{name}`

**Checkpoint**: схема разворачивается, unit-тесты value object зелёные, истории можно вести параллельно

---

## Phase 3: User Story 1 - Выдача номера без гонки (Priority: P1) 🎯 MVP

**Goal**: клиент получает номер, который не будет выдан второй раз; повтор возвращает тот же номер

**Independent Test**: на заполненном фабриками справочнике — два одновременных запроса дают разные
номера, повтор одного из них возвращает прежний

### Step 3.1: Тесты выдачи номера

<!-- plan-meta:
allowed_paths:
  - "app/**"
  - "bootstrap/**"
  - "config/**"
  - "database/**"
  - "routes/**"
  - "resources/**"
  - "tests/**"
gate_commands:
  lint: "docker compose exec -T app ./vendor/bin/pint --test"
  type: "docker compose exec -T app ./vendor/bin/phpstan analyse --no-progress"
  test_quick: "docker compose exec -T app php artisan test --filter=Sequence"
  test_full: "make test"
tier: strong
-->

Тесты пишутся первыми и падают до Step 3.2.


- [x] T033 [P] [US1] `tests/Feature/Sequence/NextIdTest.php`: первая выдача, `formatted_id` по шаблону, `is_new: true` (FR-001, FR-005)
- [x] T034 [P] [US1] `tests/Feature/Sequence/IdempotencyTest.php`: повтор той же тройки, повтор с другим регистром и разделителем, отсутствие второй записи в реестре (FR-002, FR-007)
- [x] T035 [P] [US1] `tests/Feature/Sequence/RejectionTest.php`: незарегистрированный проект, погашенный проект, невключённый тип, погашенный тип, пустая после нормализации тема — каждый со своим `error.code` и нормализованным ключом в теле (FR-010, FR-015, FR-007a, SC-003)
- [x] T036 [P] [US1] `tests/Feature/Sequence/ListTest.php`: порядок по убыванию, поля перечня, отказ по незарегистрированному проекту (FR-006)
- [x] T037 [P] [US1] `tests/Feature/Sequence/SeedSequenceTest.php`: при `seed_sequence = 42` первая выдача возвращает 43 (FR-014a)
- [x] T038 [US1] `tests/Concurrency/ConcurrentIssueTest.php`: 50 процессов через `Process::pool` на одну пару «проект + тип», все со своей меткой старта `--at`, проверка — ровно 50 различных номеров без пропусков; набор не оборачивается в транзакцию и чистит таблицы усечением (SC-001, принцип V)
- [x] T039 [US1] Команда `app/Console/Commands/IssueIdentifier.php` — точка входа для процессов теста конкурентности: принимает `--at=<unix ms>` и ждёт до этой метки перед вызовом `SequenceIssuer`, печатает выданный номер в stdout. Дочерние процессы запускаются с тем же `APP_ENV=testing` и `DB_DATABASE=getid_test`, что и сам тест, — окружение передаётся в `Process::env()` явно, а не наследуется на удачу. Барьер обязателен: `Process::pool` стартует процессы последовательно, холодный старт Laravel занимает сотни миллисекунд, и без общей метки первый процесс успевает закоммитить транзакцию раньше, чем второй дойдёт до `lockForUpdate()` — тест станет зелёным на заведомо сломанной реализации
- [x] T095 [US1] `tests/Concurrency/ConcurrentSameNameTest.php`: 10 процессов запрашивают номер с **одной и той же** темой одновременно, с тем же барьером `--at`, что и T038; проверка — все получают один номер, в реестре ровно одна запись, и `project_key_type.last_sequence` сдвинулся ровно на единицу — проигравшие гонку не сожгли номера (SC-002, FR-004a, FR-004b). Отличается от T038, где темы разные: тот проверяет сериализацию счётчика, этот — разрешение столкновения по уникальному индексу
- [x] T096 [P] [US1] `tests/Feature/Sequence/StorageFailureTest.php`: отказ хранилища в момент выдачи — клиент получает ошибку, номер не выдан, счётчик не сдвинут, повтор после восстановления безопасен (FR-004c)
- [x] T097 [P] [US1] `tests/Feature/Sequence/ImmutabilityTest.php`: реестр не допускает обновления и удаления записи; `down()` миграции реестра на непустой таблице бросает исключение, а на пустой отрабатывает (FR-004, FR-016)
- [x] T098 [P] [US1] `tests/Feature/UnauthenticatedAccessTest.php`: каждый маршрут `/api/v1/*` без заголовка авторизации и с отозванным токеном отвергается (FR-020); проект не заводится сам по факту обращения (FR-011)

### Step 3.2: Доменный сервис и REST

<!-- plan-meta:
allowed_paths:
  - "app/**"
  - "bootstrap/**"
  - "config/**"
  - "database/**"
  - "routes/**"
  - "resources/**"
  - "tests/**"
gate_commands:
  lint: "docker compose exec -T app ./vendor/bin/pint --test"
  type: "docker compose exec -T app ./vendor/bin/phpstan analyse --no-progress"
  test_quick: "docker compose exec -T app php artisan test --filter=Sequence"
  test_full: "make test"
tier: strong
-->

- [x] T040 [US1] `app/Domain/Sequence/IssuedIdentifier.php` — неизменяемый результат выдачи (номер, форматированный вид, признак новизны, исходная тема)
- [x] T041 [US1] `app/Domain/Sequence/SequenceIssuer.php`: поиск существующей записи по `(project, type, name_slug)` до транзакции; иначе транзакция с `lockForUpdate()` на строке `project_key_type`, следующий номер `GREATEST(seed_sequence, last_sequence) + 1`, инкремент счётчика, вставка (research.md §R1, FR-003, FR-004a)
- [x] T042 [US1] Обработать `UniqueConstraintViolationException` **снаружи** `DB::transaction()`, а не внутри: исключение обязано выйти из замыкания, чтобы транзакция откатилась целиком вместе с инкрементом счётчика. Если поймать его внутри и перечитать запись там же, инкремент закоммитится и номер сгорит — пропуск, который запрещает FR-004a. После отката перечитать запись по `name_slug`: нашлась — это штатный повтор, вернуть её с `is_new: false`; не нашлась — столкнулся `sequence_number`, то есть сериализация не сработала: дефект, запись в лог приложения и ошибка сервера (FR-004a, FR-004b). `DB::transaction()` вызывать с числом попыток больше единицы, чтобы редкий deadlock не дошёл до клиента (FR-003)
- [x] T043 [P] [US1] `app/Http/Requests/Api/NextSequenceRequest.php` и `ListSequenceRequest.php` по схемам contracts/rest-api.yaml
- [x] T044 [P] [US1] `app/Http/Resources/IssuedIdentifierResource.php` и `IdentifierListResource.php` — форма ответа из контракта, `name` отдаётся исходный, не нормализованный
- [x] T045 [US1] `app/Http/Controllers/Api/SequenceController.php` — два действия, вся работа делегируется `SequenceIssuer`
- [x] T046 [US1] Зарегистрировать `POST /api/v1/sequence/next` и `GET /api/v1/sequence/list` в `routes/api.php` под `Route::prefix('api/v1')`: `apiPrefix` в `bootstrap/app.php` пуст (T028a), автоматического префикса нет

**Checkpoint**: MVP работает — номера выдаются, гонки нет, повтор идемпотентен

---

## Phase 4: User Story 2 - Администратор ведёт справочники (Priority: P2)

**Goal**: администратор заводит проекты и типы, включает типы в проектах и задаёт начальную нумерацию

**Independent Test**: администратор проходит цепочку «завести проект → завести тип → включить тип»;
обычный пользователь получает отказ на каждой операции и не видит перечня проектов

### Step 4.1: Тесты справочников

<!-- plan-meta:
allowed_paths:
  - "app/**"
  - "bootstrap/**"
  - "config/**"
  - "database/**"
  - "routes/**"
  - "resources/**"
  - "tests/**"
gate_commands:
  lint: "docker compose exec -T app ./vendor/bin/pint --test"
  type: "docker compose exec -T app ./vendor/bin/phpstan analyse --no-progress"
  test_quick: "docker compose exec -T app php artisan test --filter=Admin"
  test_full: "make test"
tier: standard
-->

- [x] T047 [P] [US2] `tests/Feature/Admin/ProjectCrudTest.php`: заведение с вычислением ключа из адреса, переименование, гашение, повторный адрес как ошибка валидации (FR-008, FR-012)
- [x] T048 [P] [US2] `tests/Feature/Admin/KeyTypeCrudTest.php`: заведение, валидация шаблона (нет номера, неизвестный плейсхолдер), гашение (FR-013, FR-013a)
- [x] T049 [P] [US2] `tests/Feature/Admin/ProjectKeyTypeTest.php`: замена набора типов, `seed_sequence`, запрет `seed_sequence` ниже выданного, запрет включения погашенного типа, продолжение нумерации после выключения и повторного включения (FR-014, FR-014a, FR-014b, Edge Cases)
- [x] T050 [P] [US2] `tests/Feature/Admin/AuthorizationTest.php`: обычный пользователь получает 403 на каждой административной операции и не получает перечня проектов; 403 приходит и для несуществующего идентификатора, то есть проверка роли срабатывает до поиска сущности, и по коду ответа нельзя узнать, существует ли объект (FR-017)
- [x] T051 [P] [US2] `tests/Feature/ProjectResolveTest.php`: SSH- и HTTPS-формы дают один ключ, незарегистрированный проект отдаёт `registered: false` и `hint`, неразбираемый адрес — 422, перечень чужих проектов не раскрывается (FR-009, FR-010)

### Step 4.2: Административные операции

<!-- plan-meta:
allowed_paths:
  - "app/**"
  - "bootstrap/**"
  - "config/**"
  - "database/**"
  - "routes/**"
  - "resources/**"
  - "tests/**"
gate_commands:
  lint: "docker compose exec -T app ./vendor/bin/pint --test"
  type: "docker compose exec -T app ./vendor/bin/phpstan analyse --no-progress"
  test_quick: "docker compose exec -T app php artisan test --filter=Admin"
  test_full: "make test"
tier: strong
-->

- [x] T052 [P] [US2] `app/Policies/ProjectPolicy.php` и `app/Policies/KeyTypePolicy.php`, регистрация в `AppServiceProvider`
- [x] T053 [P] [US2] FormRequest'ы в `app/Http/Requests/Api/Admin/`: `StoreProjectRequest`, `UpdateProjectRequest`, `StoreKeyTypeRequest`, `UpdateKeyTypeRequest`, `SetProjectKeyTypesRequest` — правило валидации шаблона опирается на `IdentifierFormat`, правило `seed_sequence` сверяется с `last_sequence`
- [x] T054 [P] [US2] Ресурсы `app/Http/Resources/ProjectResource.php`, `KeyTypeResource.php`, `EnabledKeyTypeResource.php`
- [x] T055 [US2] `app/Http/Controllers/Api/Admin/ProjectController.php` — перечень, заведение, изменение
- [x] T056 [US2] `app/Http/Controllers/Api/Admin/KeyTypeController.php` — перечень, заведение, изменение
- [x] T057 [US2] `app/Http/Controllers/Api/Admin/ProjectKeyTypeController.php` — полная замена набора типов проекта: тип, пропавший из набора, получает `is_enabled = false`, вернувшийся — `true`; строки связи не удаляются никогда, иначе теряется счётчик (FR-016, data-model.md §project_key_type)
- [x] T058 [US2] `app/Http/Controllers/Api/ProjectResolveController.php` — нормализация адреса и статус проекта, без раскрытия перечня
- [x] T059 [US2] Зарегистрировать административные маршруты и `GET /api/v1/projects/resolve` в `routes/api.php`

**Checkpoint**: справочник ведётся администратором, реестр закрыт для остальных

---

## Phase 5: User Story 3 - Вход сотрудника и токены (Priority: P3)

**Goal**: сотрудник домена `@cas.ai` входит через Google и управляет своими токенами

**Independent Test**: вход корпоративным аккаунтом создаёт пользователя и даёт создать токен; вход
посторонним доменом отвергается без создания учётной записи

### Step 5.1: Тесты входа и токенов

<!-- plan-meta:
allowed_paths:
  - "app/**"
  - "bootstrap/**"
  - "config/**"
  - "database/**"
  - "routes/**"
  - "resources/**"
  - "tests/**"
gate_commands:
  lint: "docker compose exec -T app ./vendor/bin/pint --test"
  type: "docker compose exec -T app ./vendor/bin/phpstan analyse --no-progress"
  test_quick: "docker compose exec -T app php artisan test --filter=Auth"
  test_full: "make test"
tier: standard
-->

- [x] T060 [P] [US3] `tests/Feature/Auth/GoogleLoginTest.php`: корпоративный домен проходит, посторонний отвергается и пользователь не создаётся, адрес из `ADMIN_EMAILS` получает роль администратора при первом входе (FR-018, FR-021)
- [x] T061 [P] [US3] `tests/Feature/Auth/TokenManagementTest.php`: несколько именованных токенов, значение показывается один раз, отзыв одного не трогает остальные (FR-019, FR-019a)
- [x] T062 [P] [US3] `tests/Feature/Auth/AdminLifecycleTest.php`: снятие роли у последнего администратора отвергается; деактивация сотрудника сносит его токены и сохраняет авторство в реестре (FR-021a, FR-021b)
- [x] T063 [P] [US3] `tests/Feature/Auth/RateLimitTest.php`: превышение порога даёт 429 с кодом, отличным от отказа по правам (FR-020a)
- [x] T099 [P] [US3] `tests/Feature/Auth/ProviderOutageTest.php`: при недоступности Google вход людей отвергается, а запросы с уже выданными токенами продолжают обслуживаться — проверка токена не обращается к провайдеру (FR-021c)

### Step 5.2: Google OAuth, токены и интерфейс

<!-- plan-meta:
allowed_paths:
  - "app/**"
  - "bootstrap/**"
  - "config/**"
  - "database/**"
  - "routes/**"
  - "resources/**"
  - "tests/**"
gate_commands:
  lint: "docker compose exec -T app ./vendor/bin/pint --test"
  type: "docker compose exec -T app ./vendor/bin/phpstan analyse --no-progress"
  test_quick: "docker compose exec -T app php artisan test --filter=Auth"
  test_full: "make test"
tier: strong
-->

- [x] T064 [US3] `config/services.php` — секция `google`; `config/getid.php` — `allowed_email_domain`, `admin_emails`, `api_log_retention_days`
- [x] T065 [US3] `app/Http/Controllers/Web/GoogleAuthController.php` — редирект и колбэк, проверка домена до создания пользователя, повышение по `ADMIN_EMAILS`
- [x] T066 [P] [US3] `app/Http/Controllers/Web/TokenController.php` — перечень, создание, отзыв; значение токена кладётся в flash один раз
- [x] T067 [P] [US3] `app/Actions/DeactivateUser.php` — гашение сотрудника со сносом токенов и проверкой «последний администратор»
- [x] T101 [P] [US3] Консольные команды управления пользователями — единственный путь для этих операций (FR-021): `app/Console/Commands/SetUserRoleCommand.php` (`user:role {email} {admin|member}`, отказ при снятии роли у последнего администратора — FR-021a) и `app/Console/Commands/DeactivateUserCommand.php` (`user:deactivate {email}`, тонкая обёртка над `App\Actions\DeactivateUser` — FR-021b). Тесты — в `tests/Feature/Auth/AdminLifecycleTest.php` (T062)
- [x] T068 [P] [US3] Blade: `resources/views/layouts/app.blade.php`, `auth/login.blade.php`, `tokens/index.blade.php`
- [x] T069 [P] [US3] Blade административных экранов: `admin/projects/index.blade.php`, `admin/key-types/index.blade.php`
- [x] T070 [US3] Маршруты в `routes/web.php`: вход, кабинет токенов, административные экраны под Gate. Страница входа называется `login`: web-гостей `Authenticate` отправляет на `route('login')` (`bootstrap/app.php`), и другое имя даст 500 на любом закрытом экране. Все маршруты именованные (`login`, `auth.google.redirect`, `auth.google.callback`, `tokens.index`, `admin.projects.index`, `admin.key-types.index`) — на `tokens.index` опирается проверка схемы ссылок после выкладки в quickstart.md

**Checkpoint**: сервисом можно пользоваться без ручной правки базы

---

## Phase 6: User Story 4 - AI-ассистент через MCP (Priority: P4)

**Goal**: ассистент получает номер, не спрашивая человека о ключе проекта

**Independent Test**: подключить сервер клиентом, вызвать `resolve_project` и `next_id` — результат
совпадает с REST на тех же данных

### Step 6.1: Тесты MCP

<!-- plan-meta:
allowed_paths:
  - "app/**"
  - "bootstrap/**"
  - "config/**"
  - "database/**"
  - "routes/**"
  - "resources/**"
  - "tests/**"
gate_commands:
  lint: "docker compose exec -T app ./vendor/bin/pint --test"
  type: "docker compose exec -T app ./vendor/bin/phpstan analyse --no-progress"
  test_quick: "docker compose exec -T app php artisan test --testsuite=Mcp"
  test_full: "make test"
tier: standard
-->

- [x] T071 [P] [US4] `tests/Mcp/ResolveProjectToolTest.php`: схема инструмента, зарегистрированный и незарегистрированный проект, неразбираемый адрес как `Response::error`
- [x] T072 [P] [US4] `tests/Mcp/NextIdToolTest.php`: выдача, идемпотентность, отказ с текстом, который начинается с кода причины из REST-контракта (`project_not_registered: …`) и называет ключ и следующий шаг (FR-024)
- [x] T073 [P] [US4] `tests/Mcp/ListIdentifiersToolTest.php`: порядок и состав перечня
- [x] T074 [US4] `tests/Mcp/ParityWithRestTest.php`: для каждой пары «tool ↔ endpoint» результат на одних и тех же входных данных совпадает, включая отказы — код причины в MCP совпадает с `error.code` REST для всех четырёх причин из FR-015 (FR-024)
- [x] T075 [P] [US4] `tests/Mcp/AuthorizationTest.php`: обращение без заголовка авторизации отвергается на маршруте, до инструмента

### Step 6.2: MCP-сервер и инструменты

<!-- plan-meta:
allowed_paths:
  - "app/**"
  - "bootstrap/**"
  - "config/**"
  - "database/**"
  - "routes/**"
  - "resources/**"
  - "tests/**"
gate_commands:
  lint: "docker compose exec -T app ./vendor/bin/pint --test"
  type: "docker compose exec -T app ./vendor/bin/phpstan analyse --no-progress"
  test_quick: "docker compose exec -T app php artisan test --testsuite=Mcp"
  test_full: "make test"
tier: strong
-->

- [x] T076 [US4] `app/Mcp/Servers/GetIdServer.php` — имя, инструкция сервера, регистрация трёх инструментов
- [x] T077 [P] [US4] `app/Mcp/Tools/ResolveProjectTool.php` — схема и текст описания из [contracts/mcp-tools.md](./contracts/mcp-tools.md), включая фразу о том, что origin читает клиент (FR-023)
- [x] T078 [P] [US4] `app/Mcp/Tools/NextIdTool.php` — вызывает `SequenceIssuer`, своей логики выдачи не содержит (принцип VI)
- [x] T079 [P] [US4] `app/Mcp/Tools/ListIdentifiersTool.php`
- [x] T080 [US4] Зарегистрировать сервер в `routes/api.php`: `Mcp::web('/mcp', GetIdServer::class)->middleware(['auth:sanctum', 'throttle:getid'])` — тот же именованный limiter, что у REST (T028), отдельного файла маршрутов пакет не заводит (FR-020a, FR-022)

**Checkpoint**: `claude mcp add --transport http` подключает сервер, номер выдаётся из сессии ассистента

---

## Phase 7: User Story 5 - Журнал обращений (Priority: P5)

**Goal**: восстановимо, кто и когда запрашивал номера

**Independent Test**: обращение к API и к MCP оставляет запись с пользователем, параметрами, кодом
ответа и длительностью; сбой записи не отменяет выдачу

### Step 7.1: Тесты журнала

<!-- plan-meta:
allowed_paths:
  - "app/**"
  - "bootstrap/**"
  - "config/**"
  - "database/**"
  - "routes/**"
  - "resources/**"
  - "tests/**"
gate_commands:
  lint: "docker compose exec -T app ./vendor/bin/pint --test"
  type: "docker compose exec -T app ./vendor/bin/phpstan analyse --no-progress"
  test_quick: "docker compose exec -T app php artisan test --filter=ApiLog"
  test_full: "make test"
tier: standard
-->

- [x] T081 [P] [US5] `tests/Feature/ApiLoggingTest.php`: состав записи для REST и для MCP — пользователь, токен, endpoint, параметры, код, длительность; сетевой адрес не пишется (FR-025, FR-024a)
- [x] T082 [P] [US5] `tests/Feature/ApiLoggingFailureTest.php`: при падении записи клиент получает выданный номер, а не ошибку (FR-026)
- [x] T083 [P] [US5] `tests/Feature/PruneApiLogsTest.php`: чистка сносит записи старше горизонта и не трогает реестр

### Step 7.2: Middleware журнала и чистка

<!-- plan-meta:
allowed_paths:
  - "app/**"
  - "bootstrap/**"
  - "config/**"
  - "database/**"
  - "routes/**"
  - "resources/**"
  - "tests/**"
gate_commands:
  lint: "docker compose exec -T app ./vendor/bin/pint --test"
  type: "docker compose exec -T app ./vendor/bin/phpstan analyse --no-progress"
  test_quick: "docker compose exec -T app php artisan test --filter=ApiLog"
  test_full: "make test"
tier: standard
-->

- [x] T084 [US5] `app/Http/Middleware/LogApiRequest.php` — пользователь и снимок имени токена (`$request->user()->currentAccessToken()->name`, см. data-model.md §api_logs), замер длительности, запись после ответа, перехват исключения записи в лог приложения; сохраняемый `payload` обрезается сверху (4 КБ), чтобы одна крупная посылка не раздувала журнал
- [x] T085 [US5] Проверить, что `LogApiRequest` уже зарегистрирован на группах `api` и `/mcp` в T028a, и что записи появляются для обеих поверхностей; сам `bootstrap/app.php` здесь не правится
- [x] T086 [P] [US5] `app/Console/Commands/PruneApiLogs.php` и регистрация в планировщике `routes/console.php` с горизонтом из `config/getid.php`

**Checkpoint**: обращения журналируются, журнал не растёт бесконечно

### Step 7.3: Решения автора по итогам проверки S2

<!-- plan-meta:
allowed_paths:
  - "app/**"
  - "bootstrap/**"
  - "config/**"
  - "database/**"
  - "routes/**"
  - "resources/**"
  - "tests/**"
gate_commands:
  lint: "docker compose exec -T app ./vendor/bin/pint --test"
  type: "docker compose exec -T app ./vendor/bin/phpstan analyse --no-progress"
  test_quick: "docker compose exec -T app php artisan test --filter=Sequence"
  test_full: "make test"
tier: strong
-->

Решения автора от 2026-09-23 по отступлениям, которые подтвердила проверка S2. Документы уже приведены
к ним (spec.md FR-005, FR-013, FR-015, Edge Cases; data-model.md §identifiers; contracts/rest-api.yaml).

- [x] T105 Хранить `formatted_id` в `identifiers` (FR-005, data-model.md §identifiers): колонка `varchar(255) NOT NULL` в исходной миграции реестра — приложение не развёрнуто, поэтому правка на месте, как в T010. `SequenceIssuer` записывает вид по шаблону в момент выдачи, повтор, перечень, REST и MCP читают сохранённое значение. Тест: после `PATCH` шаблона типа ранее выданный номер в повторе и в перечне сохраняет прежний вид, а новая выдача получает новый
- [x] T106 Закрепить тестом поведение FR-015 для повтора: после гашения проекта, типа и пары повтор уже выданной темы возвращает прежний номер с `is_new: false` через REST и MCP, а новая тема получает отказ со своим кодом. Код уже так работает (S2), теста на этот сценарий нет
- [x] T107 Применять `additionalProperties: false` и `minProperties: 1` из contracts/rest-api.yaml на всех операциях, где они объявлены: неизвестное поле тела и пустой `PATCH` дают 422 `ValidationError`. Одно общее средство для всех FormRequest, а не правило в каждом; MCP-инструменты используют те же FormRequest-правила и получают то же поведение
- [x] T108 Закрепить тестом правило `seed_sequence` из контракта: тип в `PUT …/key-types` без `seed_sequence` сохраняет текущий seed, у новой пары он 0

**Checkpoint**: выданный идентификатор неизменен, контракт применяется буквально

---

## Phase 8: Polish & Cross-Cutting Concerns

### Step 8.1: Прогон качества

<!-- plan-meta:
allowed_paths:
  - "app/**"
  - "bootstrap/**"
  - "config/**"
  - "database/**"
  - "routes/**"
  - "resources/**"
  - "tests/**"
  - "specs/001-incremental-id-registry/quickstart.md"
gate_commands:
  lint: "docker compose exec -T app ./vendor/bin/pint --test"
  type: "docker compose exec -T app ./vendor/bin/phpstan analyse --no-progress"
  test_full: "make test"
tier: standard
-->

- [x] T087 Прогнать весь набор в Docker: `make test` — зелёный, включая `tests/Concurrency` (принцип VII, SC-006)
- [x] T088 [P] Прогнать `vendor/bin/pint` и `vendor/bin/phpstan analyse` до чистого вывода
- [x] T089 [P] Пройти [quickstart.md](./quickstart.md) целиком на чистом окружении, включая подключение MCP и выдачу номера из сессии ассистента без ручного ввода ключа проекта; расхождения исправить в самом quickstart (SC-004, SC-005)
### Step 8.2: Документация и закрытие пакета

<!-- plan-meta:
allowed_paths:
  - "docs/**"
  - "CLAUDE.md"
  - "README.md"
  - "specs/001-incremental-id-registry/**"
  - ".specify/memory/constitution.md"
gate_commands:
  test_full: "make test"
tier: standard
-->

- [x] T090 [P] Написать `CLAUDE.md` репозитория: непрозрачные решения (счётчик в pivot, нормализация ключа, граница MCP-поверхности) и указатели на живые документы
- [x] T091 [P] Написать ADR `docs/adr/adr-001-sequence-locking.md` — блокировка строки-счётчика против `SELECT MAX() FOR UPDATE`, с отвергнутыми альтернативами из research.md §R1
- [x] T092 [P] Написать ADR `docs/adr/adr-002-mcp-surface-boundary.md` — почему административные операции не публикуются в MCP
- [x] T093 Сократить принципы конституции до строк-указателей на CLAUDE.md и ADR там, где источник появился (Governance конституции)
- [x] T094 Закрыть пакет: отметить выполненные пункты [checklists/integrity.md](./checklists/integrity.md), обновить статус в [spec.md](./spec.md)
- [x] T102 Записать в ledger прогнанные гейты: `~/.claude/skills/speckit-gates/scripts/gates.sh record analyze --package specs/001-incremental-id-registry` и то же для `converge` после его прогона. `/speckit-analyze` read-only и сам ничего не фиксирует, поэтому без этой записи гейт остаётся `MISSING`, хотя анализ был выполнен

---

## Dependencies & Execution Order

```text
Phase 1 (Setup)
   └─> Phase 2 (Foundational)
          ├─> Phase 3 (US1, P1) ── MVP
          ├─> Phase 4 (US2, P2)
          ├─> Phase 5 (US3, P3)
          ├─> Phase 6 (US4, P4) ── требует US1: инструменты зовут SequenceIssuer
          └─> Phase 7 (US5, P5)
                 └─> Phase 8 (Polish)
```

Истории независимы по коду, но две зависимости реальны и их не обойти:

- **US4 требует US1.** MCP-инструменты вызывают `SequenceIssuer`; без него нечего вызывать.
- **US2 наполняет справочник, которым пользуется US1.** В тестах US1 справочник создаётся
  фабриками, поэтому порядок разработки свободен, а порядок реального использования — нет.

US3 (вход) не блокирует ничего: тесты аутентифицируются через `Sanctum::actingAs`.

## Parallel Opportunities

- **Phase 1**: T003, T004, T004a, T005 (docker и Makefile), T007, T008 — разные файлы
- **Phase 2**: миграции T011, T012, T015 параллельны между собой; T013 и T014 после T011 и T012
  из-за внешних ключей. Модели T016–T020 параллельны. Тесты T022–T024 и реализации T025–T027
  параллельны попарно
- **Phase 3**: тесты T033–T037 и T096–T098 параллельны; T038, T039 и T095 пишутся до реализации и идут одним куском (общий барьер), зелёными становятся после T041
- **Phase 4**: тесты T047–T051 параллельны; T052–T054 параллельны, контроллеры T055–T058 — после них
- **Phase 6**: инструменты T077–T079 параллельны после T076
- **Phase 8**: T088–T089 параллельны в Step 8.1; T090–T092 параллельны в Step 8.2, T093, T094 и T102 после них

## Implementation Strategy

**MVP — Phase 1 + Phase 2 + Phase 3.** На этом объёме сервис уже решает исходную задачу: номера
выдаются без гонки и без дублей. Справочник на этом этапе наполняется фабриками или `tinker`.

**Второй прирост — Phase 4.** Справочник становится управляемым, реестр закрывается от посторонних
ключей.

**Третий прирост — Phase 5 и Phase 6.** Сервисом начинают пользоваться люди и ассистенты без правки
базы руками.

**Четвёртый — Phase 7 и Phase 8.** Журнал, качество, документация, закрытие пакета.

Размер работы — больше одной-двух сессий, поэтому исполнитель здесь `/orchestrate-plan`, а не
`/speckit-implement`. Перед запуском оркестрации пакет проходит `/plan` и `/plan-fix`: они приводят
шаги к каноническим заголовкам и проставляют `plan-meta`.

## Правки после `/speckit-analyze` (2026-09-20)

Задачи T095–T099 добавлены по итогам анализа и стоят в своих фазах, а не в конце файла; порядок
выполнения задаёт фаза, а не номер. Каждая закрывает требование, у которого не было ни одной задачи:

| Задача | Требование | Чего не хватало |
|--------|-----------|-----------------|
| T095 | SC-002, FR-004b | одновременный повтор **одной** темы не проверялся — T038 гоняет разные темы |
| T096 | FR-004c | отказ хранилища в середине выдачи |
| T097 | FR-004, FR-016 | запрет изменения и удаления записей реестра выражен схемой, но не проверялся |
| T098 | FR-020, FR-011 | обращение к REST без токена проверялось только для MCP-поверхности |
| T099 | FR-021c | недоступность Google не должна мешать уже выданным токенам |

Там же исправлено расхождение с документацией `laravel/mcp`: сервер регистрируется в
`routes/api.php`, отдельного файла маршрутов пакет не создаёт.

### Второй прогон (2026-09-23)

| Правка | Чего не хватало |
|--------|-----------------|
| T104 | тесты по умолчанию шли бы на `sqlite :memory:`: блокировки не проверяются, процессы теста гонки не видят общей базы, suites `Mcp` и `Concurrency` не запускаются |
| T042, T095 | столкновение по теме обрабатывалось внутри транзакции — инкремент счётчика коммитился, номер сгорал вопреки FR-004a |
| T039 | окружение дочерних процессов теста гонки не было закреплено |
| T084, T070 | журнал не знал о снимке имени токена; маршруты, на которые опирается quickstart, не были именованы |

## Session Map

- [x] S1 (~600K) Steps 1.1, 2.1, 2.2, 2.3, 2.4 — done 2026-09-23
- [x] S2 (~600K) Steps 3.1, 3.2, 4.1, 4.2, 5.1 — done 2026-09-23
- [x] S3 (~550K) Steps 5.2, 6.1, 6.2, 7.1, 7.2 — done 2026-09-23
- [ ] S4 (~310K) Steps 7.3, 8.1, 8.2 — **current**

## Progress Log

### S1.step-1.1 — 2026-09-23
**Completed steps:** 1.1
**Commits:** 37f37a4

### S1.step-2.1 — 2026-09-23
**Completed steps:** 2.1
**Commits:** d67b5bd

### S1.step-2.2 — 2026-09-23
**Completed steps:** 2.2
**Commits:** cd3cf51

### S1.step-2.3 — 2026-09-23
**Completed steps:** 2.3
**Commits:** b245460

### S1.step-2.4 — 2026-09-23
**Completed steps:** 2.4
**Commits:** ae5c997

### S1 — observations (2026-09-23, dispatch 1)
plan-wrong: this harness has no TaskCreate tool, so the bundle was tracked without it.
plan-wrong: T001: the laravel/laravel 13.10.1 skeleton ships CLAUDE.md and AGENTS.md, a Laravel Boost bootstrap prompt (curl|bash a host PHP, composer require laravel/boost). Both were excluded from the rsync; T090 still owns the repo CLAUDE.md.
plan-wrong: T104: <env force='true'> alone does not isolate tests. PHPUnit force writes only putenv and $_ENV (vendor/phpunit/phpunit/src/TextUI/Configuration/PhpHandler.php:133-150), while Laravel Env reads $_SERVER first (ServerConstAdapter), which CLI fills from the container environment. A hostile DB_CONNECTION/DB_DATABASE sent the suite elsewhere (reproduced); fixed with <server> twins next to each forced <env>.
plan-wrong: T104 names docker/mysql/init/01-test-db.sql, but plain SQL cannot read the grantee from DB_USERNAME. Shipped as an executable 01-test-db.sh. Docker Desktop reports bind-mounted files as executable to root, so a non-executable script relying on the entrypoint's docker_process_sql fails on macOS (container exit 126).
plan-wrong: T007: the php:8.3-fpm image has no php.ini, and Larastan crashes at the built-in 128M. The fixed gate command cannot pass --memory-limit, so the Dockerfile sets 512M.
plan-wrong: T007: pestphp/pest ^4.7 needs composer -W (it pins phpunit 12.5.33 under the skeleton's locked 12.5.35); Pest 5 needs PHP 8.4. T002's pin to 8.3 is actually enforced by config.platform.php=8.3.0, not by the php requirement.
plan-wrong: data-model.md projects: the 767-byte index limit belongs to COMPACT/REDUNDANT rows. MySQL 8.0 and MariaDB 10.2+ default to DYNAMIC (3072 bytes), so UNIQUE on utf8mb4 varchar(255) needs neither a prefix nor narrowing.
plan-wrong: data-model.md identifiers: INDEX (project_id, key_type_id, sequence_number DESC) duplicates the UNIQUE on the same columns. EXPLAIN on MySQL 8.0.44 shows that unique index with 'Backward index scan' and no filesort, so the extra index was not created.
plan-wrong: data-model.md specifies utf8mb4_unicode_ci everywhere, which folds cafe=café, елка=ёлка and strasse=straße (verified in MySQL) and would merge distinct themes, against FR-007. identifiers.name_slug and projects.key use utf8mb4_bin.
plan-wrong: T028a/T080: Mcp::web('/mcp') inside routes/api.php lands at /api/mcp under the default apiPrefix, contradicting contracts/mcp-tools.md. Resolved with apiPrefix '', so T046 must declare REST under Route::prefix('api/v1') in routes/api.php.
plan-wrong: T028a: a separate MCP middleware group would be dead config, because the MCP route lives in routes/api.php and inherits the api group. T080's repeated auth:sanctum/throttle:getid is deduplicated by Router::uniqueMiddleware; a test pins that 60 requests pass.
plan-wrong: T028a: registering the not-yet-written LogApiRequest by name in an active group makes every API request fail (the container cannot resolve the class). A pass-through class was created; T084 fills it, and T085 has nothing to register.
plan-wrong: T028a/T030: without redirectGuestsTo, an API request without an Accept header returns 500 on route('login'), because Authenticate computes the redirect before any renderer runs. The API surface now gets no redirect; web still defaults to route('login'), so T070 must name its sign-in page route 'login' or edit bootstrap/app.php again.
plan-wrong: T030: abort(403) throws a plain HttpException, not AccessDeniedHttpException. Rendering is keyed by status code (403/404/429) on HttpExceptionInterface.
plan-wrong: T025/T026 throw UnparsableOrigin/EmptyDocumentName, which the plan defines only in T029 (step 2.4). The DomainRejection base and those two classes were created in 2.3.
plan-wrong: T097 overlaps what exists: the refusing down() on a non-empty table and every Eloquent write path are already tested (tests/Feature/Database/RegistrySchemaTest.php, tests/Feature/Models/IdentifierImmutabilityTest.php). down() on an empty table issues DDL, which implicitly commits the RefreshDatabase transaction, so that half needs DatabaseMigrations or its own cleanup.
redone: docker/mysql/init/01-test-db.sh went from sourced to executable after the mysql container exited 126 on the virtiofs -x quirk.
redone: phpunit.xml DB isolation: <env force> plus <server>, after the hostile-env probe failed. The failed run left a stray sqlite file 'getid' in the repo root, which was deleted.
redone: IdentifierImmutabilityTest datasets were double-wrapped closures. Pest passes Closure-typed dataset values uninvoked, so nothing ran, and the red came from the test itself. Flattened; the instance touch() case became builder touch(), because a same-second touch is a no-op save.
redone: Project/KeyType belongsToMany defaulted to table key_type_project, since using() does not set the table; now explicit 'project_key_type'.
redone: IdentifierFormat moved to PREG_UNMATCHED_AS_NULL and a separate NUMBER fragment after PHPStan's regex-shape inference wrongly assumed the width group is always set.
redone: RenderApiErrors went from class-keyed renderers (abort(403) slipped through) to one status-keyed renderer. The API-path predicate moved to App\Http\ApiSurface once redirectGuestsTo needed it too.
decided: host port is APP_PORT (8080 in .env.example, 8090 in the local .env) and MySQL is not published to the host. Breaks host-side DB tools, which then need a configurable port mapping.
decided: compose project name get-id (containers get-id-*-1, volume get-id_mysql-data), no container_name. The Makefile exports the host UID/GID as image build args; a plain 'docker compose build' uses 1000:1000.
decided: make up depends on a .env file target (compose reads MySQL credentials and the port from it). init guards key:generate so a rerun keeps APP_KEY. make test runs up, then composer test (config:clear plus all four suites).
decided: .gitignore left untouched: it already carried /vendor, /public/build and /storage/*.key.
decided: phpstan.neon analyses app, bootstrap/app.php, config, database and routes, not tests/ (Pest binds $this at runtime). tests/Pest.php binds TestCase+RefreshDatabase to Feature and Mcp only; Concurrency stays unbound so T038 runs outside a transaction.
decided: users lose password, email_verified_at and the password_reset_tokens table. DatabaseSeeder seeds no user, so quickstart's User::first() is the Google-signed admin. role is not mass-assignable, and a new User is 'member' in memory.
decided: ProjectKeyType::nextSequence() is the single home of GREATEST(seed,last)+1 for the issuer, resolve and admin resources. T041 must still read it under the row lock.
decided: Identifier immutability is enforced in the base query builder (update/upsert/delete), which closes save, update, increment, touch, upsert and relation deletes. Raw DB::table and truncate stay open (the Concurrency suite truncates).
decided: ProjectKey accepts schemes ssh/git/http/https/git+ssh/ssh+git and follows git's scp rule (a colon before the first slash). Path segments are letters, digits and ._~- only, and IPv6 literal hosts are rejected. Breaks any origin carrying other characters, which becomes origin_unparsable.
decided: DocumentName keeps combining marks (\p{M}); otherwise Devanagari and similar scripts split into hyphens. NFC is applied both before and after lowercasing.
decided: IdentifierFormat widths are 1-99; {number:Nd} without the leading 0 and stray braces are rejected, and format() refuses numbers below 1.
decided: rejection messages are Russian and name the normalized key and the next step. MCP (T072) can prefix them with errorCode(). A DomainRejection renders 422 on any path, not only the API.
decided: LogApiRequest sits after auth:sanctum and throttle:getid, so the journal sees only authenticated, in-budget requests; 401 and 429 are not journaled. Changing that is a group-order edit in bootstrap/app.php.
decided: the limiter keys on 'token:<id>' for a PersonalAccessToken and falls back to 'ip:'; the fallback is unreachable today because auth runs first.
decided: KeyTypeSeeder uses firstOrCreate, so reseeding on every 'make migrate' never overwrites an administrator's edits.
decided: the skeleton's tests/Unit/ExampleTest.php was removed in 2.3; tests/Feature/ExampleTest.php (GET / returns 200) stays until T070 replaces '/'.

### S1 — dispatch 1 (2026-09-23)
`kind=bundle agent=implementer tier=strong model=claude-opus-5-5 effort=xhigh turns=103 minutes=35.2 input=206 cache_write=348952 cache_read=22169222 output=181069`

### S1 — dispatch 2 (2026-09-23)
`kind=spec-verify agent=verifier model=claude-sonnet-5 effort=high turns=27 minutes=4.3 input=54 cache_write=112070 cache_read=1838713 output=24401`

S1 verify: 2.1 и 2.4 — DEVIATES (collation `utf8mb4_bin`, нет DESC-индекса, нет отдельной группы MCP). Решение автора (2026-09-23): код верен, документы приведены к нему — commit 76602c7.

### S2.step-3.1 — 2026-09-23
**Completed steps:** 3.1
**Commits:** 1aa8d5b

### S2.step-3.2 — 2026-09-23
**Completed steps:** 3.2
**Commits:** fd9deac

### S2.step-4.1 — 2026-09-23
**Completed steps:** 4.1
**Commits:** 20fe168

### S2.step-4.2 — 2026-09-23
**Completed steps:** 4.2
**Commits:** 711b268

### S2.step-5.1 — 2026-09-23
**Completed steps:** 5.1
**Commits:** c7bd71e

### S2 — observations (2026-09-23, dispatch 3)
plan-wrong: T039 (IssueIdentifier command) sits in tests step 3.1 but calls SequenceIssuer from T041 (step 3.2); it would not type-check at 3.1, so it landed in the 3.2 commit and the 3.1 race tests were red on 'no commands in the getid namespace'.
plan-wrong: T050/FR-017 cannot hold with framework defaults: the default middleware priority runs SubstituteBindings before Authorize, so any can: check comes after the id lookup (404 for a missing id). bootstrap/app.php was edited outside T028a/T103 to rank EnsureAdministrator ahead of SubstituteBindings.
plan-wrong: data-model.md project_key_type and spec Edge Cases say a seed below the issued numbers would make the next issuance repeat one; under GREATEST(seed,last)+1 a lower seed changes nothing. The FR-014b refusal is kept as specified, and its message states the rule, not that consequence.
plan-wrong: data-model.md validation table checks seed_sequence in the FormRequest and again in the domain service; the unlocked FormRequest copy is a TOCTOU duplicate, so the check lives only in EnabledKeyTypes under the counter row lock SequenceIssuer takes (T053 carries no seed rule).
plan-wrong: identifiers has no formatted-id column, so formatted_id is derived from the key type's current template. PATCH format_template retroactively changes formatted_id of every issued number in listings and repeats, while consumer file names keep the old form. Needs a decision: freeze the template once a pair has issued, or store formatted_id at issuance.
plan-wrong: T097 overlaps S1's IdentifierImmutabilityTest and RegistrySchemaTest; ImmutabilityTest references them and adds only the API surface plus down() on an empty table, restoring the table in finally because the DDL commits the RefreshDatabase transaction.
plan-wrong: T063 and the token half of T099 are green on arrival. FR-020a was built in S1 (T028), and token checks never reach Google; the tests now pin that on real endpoints.
redone: EnabledKeyTypes first dropped pairs via $counters->except(array_keys($enabled)) on a keyBy('key_type_id') collection. Eloquent\Collection::except() filters by model primary key, not collection key, so the wrong pairs were disabled; 5 ProjectKeyTypeTest cases went red, and it became reject() on key_type_id.
redone: the three admin not-found tests passed before any route existed (404 either way); each gained a positive control on an existing id.
redone: IssueRace START_DELAY_MS went from 10 s to 5 s after measuring 50 concurrent boots finishing within 1.7 s (min waited_ms 8296 of 10000).
redone: the GoogleLoginTest ADMIN_EMAILS case signed in twice in one test, which tied it to how 5.2 treats an already-authenticated session; split into two single-sign-in tests.
decided: race tests were verified against deliberately broken issuers. Without lockForUpdate the 50-process test fails with SequenceNumberCollision. With the unique violation caught inside the transaction, the same-theme test fails with last_sequence 17 instead of 8 (nine losers burned numbers). Each child prints waited_ms and IssueRace fails if any process booted after the start mark. Breaks if a slower host needs more than 5 s for 50 boots; the test then fails loudly asking to raise START_DELAY_MS.
decided: principle I over the literal FR-015: a repeat of an existing theme returns its number even after the project, type or pair is retired; only new themes are refused. Breaks if the product wants retirement to block repeats too.
decided: refusal order is unparsable key, unknown project, empty theme, unknown type; then for a new theme project_inactive, type_inactive, type_not_enabled. The unknown project comes before the empty theme so every such refusal carries the key (SC-003). An unknown type code answers type_not_enabled, so the admin-only type list is not revealed.
decided: type codes compare case-insensitively via the key_types.code unicode_ci collation (issuer lookups, unique validation, distinct:ignore_case). Breaks if codes differing only in case must coexist.
decided: SequenceIssuer must be called outside any open transaction (documented on the class): the re-read after a lost same-theme race needs a fresh snapshot. Breaks if the MCP tool (T078) wraps the call in a transaction.
decided: a storage failure and the sequence-number collision defect both render the framework's 500 (the contract has no code for them); SequenceNumberCollision::context() puts project_key, type and sequence_number in the log.
decided: getid:issue is #[Hidden]; it issues without auth or author and exists for the race suite.
decided: admin authorization uses Gate 'administer' in EnsureAdministrator (ahead of binding) plus ProjectPolicy/KeyTypePolicy (viewAny/create/update, no delete) in the FormRequests and index actions. T070 web admin screens can use can:administer.
decided: in PUT key-types, a type sent without seed_sequence keeps its current seed (0 for a new pair) instead of the contract default 0, which would silently drop a seed of 42. Any retired type in the set is refused even if already enabled; an empty set disables every type; the response lists the pairs now issuing, ordered by code.
decided: resolve also sets a hint for a retired project, reusing the refusal messages of issuance; types lists only enabled pairs of active types.
decided: contract additionalProperties:false and minProperties:1 are not enforced: unknown fields are dropped via validated(), and an empty PATCH is a 200 no-op. Breaks if clients rely on typos being rejected.
decided: Project and KeyType got $attributes is_active=true mirroring the column default, so a created model renders is_active without a refresh.
decided: the 31 red 5.1 tests (route not defined, command not found) are committed red per the gate rule, not skipped. They fix the 5.2 surface: route names login, auth.google.redirect, auth.google.callback, tokens.index, plus tokens.store and tokens.destroy (the last two are not in T070); config getid.allowed_email_domain (string) and getid.admin_emails (list, applied only when an account is created); positional 'user:role <email> <admin|member>' and 'user:deactivate <email>' exiting non-zero on refusal; refused sign-ins redirect to route('login'); token names unique per user; revoking another user's token is a 404; the last-administrator rule counts only active admins and covers deactivation too; the callback must call Socialite::driver('google')->user() directly, since a chained stateless() would bypass Socialite::fake.

### S2 — dispatch 3 (2026-09-23)
`kind=bundle agent=implementer tier=strong model=claude-opus-5-5 effort=xhigh turns=108 minutes=35.3 input=216 cache_write=375568 cache_read=25923796 output=210545`

### S2 — dispatch 4 (2026-09-23)
`kind=spec-verify agent=verifier model=claude-sonnet-5 effort=high turns=43 minutes=4.3 input=86 cache_write=86711 cache_read=2443963 output=19487`

S2 verify: все пять шагов IMPLEMENTS; 264 passed, 31 failed — только `tests/Feature/Auth` (ждут 5.2). Подтверждены незаписанные в spec отступления, вынесены автору: повтор существующей темы после гашения (FR-015), `additionalProperties`/`minProperties` контракта не применяются, `seed_sequence` без значения сохраняет текущий вместо `default: 0`, `formatted_id` вычисляется по текущему шаблону.

### S3.step-5.2 — 2026-09-23
**Completed steps:** 5.2
**Commits:** cef448c

### S3.step-6.1 — 2026-09-23
**Completed steps:** 6.1
**Commits:** 1dfc0e9

### S3.step-6.2 — 2026-09-23
**Completed steps:** 6.2
**Commits:** e695ec4

### S3.step-7.1 — 2026-09-23
**Completed steps:** 7.1
**Commits:** 98472c2

### S3.step-7.2 — 2026-09-23
**Completed steps:** 7.2
**Commits:** 70f70eb

### S3 — observations (2026-09-23, dispatch 5)
plan-wrong: T086 names app/Console/Commands/PruneApiLogs.php, but principle IV requires the framework mechanism. ApiLog uses MassPrunable, and routes/console.php schedules model:prune --model=App\Models\ApiLog daily; no custom command exists, and PruneApiLogsTest drives model:prune.
plan-wrong: FR-024/T074: the HTTP kernel trims every string input (TrimStrings -> Str::trim), while laravel/mcp reads the raw JSON-RPC body past it. ' ADR' then answered type_not_enabled over MCP only, and a padded theme was stored as the first wording. RegistryTool trims tool arguments with the same Str::trim; a padded parity case pins it (verified red without the trim).
plan-wrong: contracts/mcp-tools.md prescribes Response::json, which is @internal in laravel/mcp 1.0.0. Tools return Response::structured: the same JSON as text content plus structuredContent, built by the endpoint's own JsonResource.
plan-wrong: FR-021b gap that no task covered: a deactivated employee's open browser session could keep minting tokens. The users auth provider is now 'active-users' (EloquentUserProvider withQuery whereNull deactivated_at; config/auth.php plus AppServiceProvider).
plan-wrong: T070 names only index routes for the admin screens, and quickstart administers through REST, so the web admin screens are read-only listings. Management stays on /api/v1/admin/*.
plan-wrong: T070: '/' now redirects to /tokens and the sign-in page is /login (route login). The skeleton welcome view and tests/Feature/ExampleTest.php (GET / == 200) were removed; quickstart's root still leads to the sign-in page via the redirect.
plan-wrong: TokenManagementTest 'revoking one token...' (5.1) authenticated its web DELETE through sanctum. The preceding API call leaves sanctum as the default guard in the test app, and withToken keeps the bearer header. Fixed with actingAs(user, 'web') before the DELETE; production is unaffected (default guard web per request).
plan-wrong: data-model api_logs says the payload is cut to 4 KB, but cut JSON text is invalid for a json column. An oversized payload is stored as {truncated, bytes, head}, with head cut on a UTF-8 boundary so the whole entry fits in 4096 unescaped bytes.
plan-wrong: T085 had nothing to do: LogApiRequest already sits in the api group, which /mcp inherits; ApiLoggingTest has an MCP case.
redone: ApiLoggingTest payload assertions went from toBe to toEqual at 7.2. MySQL's json column reorders object keys, so the exact-order comparison from 7.1 failed on correct content.
redone: PruneApiLogsTest helper misused tap() (the proxy returned save()'s bool). The scheduler test first read Schedule without bootstrapping the console kernel, which is when routes/console.php loads, and expected doubled backslashes. Both fixed before the 7.1 commit.
redone: pint rejected fully qualified class names in the tool schema docblocks, so Type is now imported. phpstan rejected ApiLog::prunable() returning Builder<self>, so it returns Builder<static>.
decided: the journal entry is written in terminate(); handle() only stashes the duration on the request. Because terminate() runs for every route middleware, that stash doubles as the 'handle ran' flag that keeps 401 and 429 out of the journal. Breaks nothing on FPM; elsewhere the write happens just before the connection closes, still without touching the body.
decided: api_logs.endpoint is the actual path ('/api/v1/admin/projects/5', '/mcp'), not the route pattern. For MCP, the tool and its arguments sit in payload (the JSON-RPC body), and status_code is the HTTP 200 even when the tool refused. Breaks journal queries that expect MCP refusals to be non-200.
decided: emails are stored lower-cased at sign-in; the user commands lower-case their argument; ADMIN_EMAILS compares case-insensitively. An account is matched by google_id, then by email (the corporate address is the identity). Sign-in refreshes google_id, name and avatar, never email or role. Breaks if the domain reissues an address to a different person.
decided: sign-in refuses an explicit email_verified=false; a missing claim passes, since Socialite fakes carry none. InvalidStateException and any GuzzleException (outage, denied consent) redirect to login with an error and a warning log.
decided: the last-administrator guard locks every active admin row, the user's own included, inside the role or deactivation transaction, so two concurrent demotions cannot each count the other as the one who stays.
decided: MCP tools apply the REST FormRequest rules and JsonResources unchanged, and refusal text is '{code}: {message}' from one place (RegistryTool::refused). resolve_project and list_identifiers carry IsReadOnly and IsIdempotent; next_id carries IsIdempotent only.
decided: the token cabinet shows the new token once, together with a ready 'claude mcp add' command. user:role and user:deactivate print English, like getid:issue; sign-in refusals on the web page are Russian.

### S3 — dispatch 5 (2026-09-23)
`kind=bundle agent=implementer tier=strong model=claude-opus-5-5 effort=xhigh turns=101 minutes=26.1 input=202 cache_write=365192 cache_read=25004167 output=149340`

### S3 — dispatch 6 (2026-09-23)
`kind=spec-verify agent=verifier model=claude-sonnet-5 effort=high turns=32 minutes=3.3 input=64 cache_write=82234 cache_read=1789092 output=13297`

S3 verify: все пять шагов IMPLEMENTS, `make test` — 359 passed. Документы приведены к коду: data-model.md §api_logs (формат обрезанного `payload`), contracts/mcp-tools.md (`Response::structured`), plan.md (команды `PruneApiLogs` нет — `model:prune`). Решения автора по отступлениям S2 записаны в spec, data-model и контракт (commit 33793b4), код — в новом Step 7.3, S4 расширен до 7.3, 8.1, 8.2.

### S4.step-7.3 — 2026-09-23
**Completed steps:** 7.3
**Commits:** d52755e

### S4.step-8.1 — 2026-09-23
**Completed steps:** 8.1
**Commits:** 4405808

### S4.step-8.2 — 2026-09-23
**Completed steps:** 8.2
**Commits:** fffe8e9

### S4 — observations (2026-09-23, dispatch 7)
plan-wrong: this harness has no TaskCreate tool, so the bundle was tracked without it.
plan-wrong: T105 did not name the three S1 tests that write registry rows by hand (RegistrySchemaTest, IdentifierImmutabilityTest, RegistryModelsTest), nor IdentifierFactory; MySQL strict mode rejects them once formatted_id is NOT NULL, so all four gained the column.
plan-wrong: T106 half-existed: the REST case (IdempotencyTest 'a repeat still returns its number after the pair is retired...', three retirement modes) came from S2; only MCP was missing. Green on arrival; calling openCounter() before find() turns all six cases red.
plan-wrong: T108 was already pinned by S2's ProjectKeyTypeTest (resent seed stays 42; a new pair shows 0 in the first test). The uncovered path, a pair dropped from the set and re-enabled without a seed, became a dataset case; resetting the seed on drop reddens only that case, and '$seed ?? 0' reddens both.
plan-wrong: T107: MCP tools validated with Laravel\Mcp\Request::validate(rules), which bypasses every FormRequest hook, so the same rules could not carry a closed body. Laravel 13.33 FormRequest already ships #[FailOnUnknownFields]; RegistryTool::validate now runs the endpoint's FormRequest itself.
plan-wrong: T107 inverted two S2 tests that pinned the lenient behaviour: ProjectCrudTest 'the key cannot be set by hand' (201 -> 422) and the rename test, which sent repo_url in the PATCH.
plan-wrong: rest-api.yaml listed no 422 on either PATCH although validation already answered 422, and nextSequence's 422 declared DomainError only while missing or unknown fields return ValidationError; 8.2 adds responses/InvalidUpdate and anyOf.
plan-wrong: data-model.md still placed the FR-014b seed check in the FormRequest plus the domain (S2 drift); aligned in 8.2 to EnabledKeyTypes under the counter lock.
plan-wrong: T089: quickstart hardcoded port 8080 with no word on APP_PORT (8080, 8090 and 8091 are all taken on this host, so make up fails with 'port is already allocated'), and the section 2 tinker check printed a UserRole enum dump instead of 'admin'.
plan-wrong: T094: both checklists were already fully ticked (integrity 36/36, requirements 16/16); only spec.md's status changed.
plan-wrong: T102: the analyze row already exists (f3eff42, '8 findings applied') and is STALE by 29 commits. A fresh row needs a real /speckit-analyze on HEAD, which this subagent cannot invoke; no row was added, since a HEAD row without a run would be false freshness.
plan-wrong: constitution Governance asks for each amendment in its own commit naming the principle and the reason; one-commit-per-step put the 1.0.1 PATCH into the 8.2 commit, with the reason in the Sync Impact Report.
redone: 8.1 added intl to the quickstart deploy extensions, claiming every issuance would 500 without it. While validating the contract YAML in 8.2, vendor/symfony/polyfill-intl-normalizer turned up (a prod dependency via symfony/string); php -n showed DocumentName producing the correct NFC slug without ext-intl. The line was reverted in 8.2 and the matching CLAUDE.md clause dropped; the 8.1 commit subject still says intl.
redone: ClosedBodyRequest failed phpstan (rules() is not declared on FormRequest); rules() is now abstract on the base.
redone: the FR-005 rationale was written twice (migration and SequenceIssuer::present); only the migration comment remains.
decided: a minProperties violation is reported under the error key 'body' (documented in rest-api.yaml InvalidUpdate); breaks clients expecting every error keyed by a field.
decided: GET query strings stay open, because the contract closes only bodies; pinned by ContractBodyTest. Breaks if the author wants closed query strings too.
decided: MCP tools build the FormRequest with $endpoint::create('/', 'POST', $arguments), then setContainer, setRedirector and validateResolved(), with the arguments as the body. No user reaches authorize(); none of the three mapped requests defines it. Breaks for a future tool mapped to an authorizing FormRequest.
decided: tool inputSchema does not advertise additionalProperties:false, since laravel/mcp Tool::toArray gives no hook; the refusal happens server-side only.
decided: formatted_id uses the template read just before the counter lock, so a template PATCH committing in that window may or may not apply to that one issuance.
decided: IdentifierFactory derives formatted_id from the key type's template, so factory rows look issued; a test needing a specific id passes it.
decided: T089 ran on a fresh clone of HEAD under COMPOSE_PROJECT_NAME=get-id-qs, APP_PORT=8092, torn down with down -v --rmi local; the live get-id stack was untouched. Google sign-in was replaced by feeding a Socialite user to SignInWithGoogle in tinker (the ADMIN_EMAILS promotion ran through production code), and the cabinet token by createToken. REST sections 4-6 ran verbatim and matched; section 7 ran over HTTP JSON-RPC: initialize, tools/list (3 tools), resolve_project, next_id, 401 without a token. In the clone make test passed 379 and make test-race 2.
decided: needs manual verification: quickstart section 2 (Google sign-in with a real OAuth client), section 3 (token cabinet UI), section 7 ('claude mcp add' and the 'claude mcp list' output, plus the assistant session; not run because it writes the user's Claude Code config), and section 'Развёртывание на LAMP' including the Cloudflare route('tokens.index') https check.
decided: the live dev database getid (8090 stack) keeps the pre-7.3 identifiers schema (no formatted_id; holds 6 users, 3 projects, 3 tokens, 0 identifiers) and was not wiped. Issuance on that stack fails until 'make fresh' (which drops that data) or the column is added by hand.
decided: spec.md status is 'Implemented' before converge and review; breaks if the author reserves the word for after review.
decided: a ruby:3-alpine image was pulled to parse the OpenAPI YAML (no YAML parser in the app container or host python) and left in the local image store.

### S4 — dispatch 7 (2026-09-23)
`kind=bundle agent=implementer tier=strong model=claude-opus-5-5 effort=xhigh turns=105 minutes=28.7 input=210 cache_write=403102 cache_read=27938372 output=161752`
