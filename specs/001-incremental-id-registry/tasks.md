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

- [ ] T001 Создать приложение Laravel 13 поверх существующего репозитория, не затирая уже лежащие файлы: `composer create-project laravel/laravel:^13.0 tmp-app --no-install`, затем `rsync -a --ignore-existing tmp-app/ ./ && rm -rf tmp-app && composer install`. Глоб `.*` не применять — он раскрывается в `.` и `..`. Skeleton несёт собственные `.gitignore` и `README.md`; `--ignore-existing` оставит наши, поэтому правила Laravel (`/vendor`, `/public/build`, `/storage/*.key`) в `.gitignore` смержить вручную
- [ ] T002 Зафиксировать PHP 8.3 в `composer.json` (`"php": "^8.3"`) и сверить, что `composer.lock` не тянет пакеты с более низкой границей
- [ ] T003 [P] Написать `docker/php/Dockerfile` (php-fpm 8.3 с `pdo_mysql`, `bcmath`, `mbstring`, `intl`), `docker/nginx/default.conf` с корнем в `public/`
- [ ] T004 [P] Написать `docker-compose.yml`: сервисы `app`, `nginx` (порт 8080), `mysql` 8.0 с томом и healthcheck; `app` зависит от `mysql` через `depends_on.condition: service_healthy`, иначе `make migrate` сразу после `make up` падает на первом запуске
- [ ] T004a [P] Написать `.dockerignore`: `vendor/`, `node_modules/`, `.git/`, `storage/logs/`, `.env` — без него содержимое этих каталогов уезжает в образ
- [ ] T005 [P] Написать `Makefile` с целями `up`, `down`, `migrate`, `fresh`, `test` (все четыре suite), `test-race` (`php artisan test --testsuite=Concurrency`), `shell` — все через `docker compose exec app`
- [ ] T006 Установить зависимости: `composer require laravel/sanctum laravel/socialite laravel/mcp` и опубликовать конфиги Sanctum
- [ ] T007 [P] Установить инструменты качества: `composer require --dev larastan/larastan laravel/pint pestphp/pest`, настроить `phpstan.neon` на уровень 6 и `pint.json`
- [ ] T008 [P] Заполнить `.env.example` ключами `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`, `ADMIN_EMAILS`, `ALLOWED_EMAIL_DOMAIN=cas.ai`, `API_LOG_RETENTION_DAYS=90`
- [ ] T104 Перевести тесты на MySQL в `phpunit.xml`: `DB_CONNECTION=mysql`, `DB_DATABASE=getid_test` вместо `sqlite`/`:memory:` из skeleton; базу `getid_test` создавать init-скриптом контейнера `mysql` (`docker/mysql/init/01-test-db.sql`). Переменные `DB_*` объявлять с `force="true"`: без него `<env>` не перекрывает значение, уже заданное окружением контейнера, и тесты молча уходят в рабочую базу — а набор `Concurrency` её усекает. Добавить testsuites `Mcp` (`tests/Mcp`) и `Concurrency` (`tests/Concurrency`) к `Unit` и `Feature`. Без этого `lockForUpdate()` не проверяется вообще — SQLite не поддерживает `SELECT ... FOR UPDATE`, — 50 процессов теста гонки получают 50 разных пустых баз в памяти, а `make test` молча пропускает `tests/Mcp` и `tests/Concurrency` (принципы V и VII)
- [ ] T009 Добавить в `Makefile` цель `init` (`cp -n .env.example .env`, `composer install`, `php artisan key:generate`) и проверить, что `make up && make init && make migrate` поднимает окружение с нуля и стандартные миграции Laravel проходят

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


- [ ] T010 Отредактировать исходную миграцию `database/migrations/0001_01_01_000000_create_users_table.php`: добавить `google_id`, `avatar_url`, `role` enum(`member`,`admin`) default `member`, `deactivated_at`; убрать `password` и `email_verified_at`. Приложение ещё не развёрнуто, поэтому отдельная миграция «поправить то, что сами же создали строкой выше» осталась бы в дереве навсегда
- [ ] T011 [P] Миграция `database/migrations/*_create_projects_table.php` по [data-model.md](./data-model.md) §projects, включая UNIQUE по `key` с учётом лимита длины индекса InnoDB
- [ ] T012 [P] Миграция `database/migrations/*_create_key_types_table.php` по §key_types
- [ ] T013 Миграция `database/migrations/*_create_project_key_type_table.php` по §project_key_type: `seed_sequence`, `last_sequence`, `is_enabled`, UNIQUE `(project_id, key_type_id)`
- [ ] T014 Миграция `database/migrations/*_create_identifiers_table.php` по §identifiers: оба UNIQUE-индекса, индекс для перечня по убыванию; `down()` дропает таблицу, только если она пуста, а на непустой бросает исключение (data-model.md §identifiers, конституция §Порядок работы)
- [ ] T015 [P] Миграция `database/migrations/*_create_api_logs_table.php` по §api_logs с индексом по `created_at`

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


- [ ] T016 [P] Модель `app/Models/Project.php`: связь `keyTypes()` через `project_key_type` с `withPivot`, scope `active()`
- [ ] T017 [P] Модель `app/Models/KeyType.php` со scope `active()`
- [ ] T018 [P] Модель `app/Models/ProjectKeyType.php` (pivot как полноценная модель — она несёт счётчик)
- [ ] T019 [P] Модель `app/Models/Identifier.php` без `update`/`delete` в публичном интерфейсе
- [ ] T020 [P] Модель `app/Models/ApiLog.php`
- [ ] T021 Дополнить `app/Models/User.php`: `HasApiTokens`, каст `role` в enum `app/Enums/UserRole.php`, метод `isAdmin()`, scope `active()`

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


- [ ] T022 [P] Тест `tests/Unit/ProjectKeyTest.php`: таблица примеров SSH/HTTPS/порт/`.git`/регистр → один ключ, уже нормализованный ключ на входе → тот же ключ (идемпотентность), плюс неразбираемые строки (FR-008, FR-008a, research.md §R5)
- [ ] T023 [P] Тест `tests/Unit/DocumentNameTest.php`: регистр, пробелы, подчёркивания, точки и прочая пунктуация, повторы разделителей, кириллица без транслитерации, одна и та же буква в составной и разложенной форме Unicode, пустой результат (FR-007, FR-007a)
- [ ] T024 [P] Тест `tests/Unit/IdentifierFormatTest.php`: `{number}`, `{number:04d}`, `{name}`, неизвестный плейсхолдер, отсутствие номера, номер шире ширины шаблона (FR-013a, Edge Cases)
- [ ] T025 [P] Реализовать `app/Domain/Project/ProjectKey.php` — разбор без `parse_url` для SCP-формы
- [ ] T026 [P] Реализовать `app/Domain/KeyType/DocumentName.php` — хранит исходную строку и slug
- [ ] T027 [P] Реализовать `app/Domain/KeyType/IdentifierFormat.php` — разбор шаблона и применение, без `sprintf` от чужой строки

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


- [ ] T028 Определить в `app/Providers/AppServiceProvider.php` именованный `RateLimiter::for('getid')`, ключующийся по `$request->user()?->currentAccessToken()?->id` с порогом 60 в минуту. Стандартный `throttle:60,1` ключуется по идентификатору пользователя, а FR-020a требует счёта **по токену** — у пользователя их несколько; вдобавок два независимых лимита на группах `api` и `/mcp` дали бы суммарно 120 запросов в минуту вместо 60
- [ ] T103 Настроить доверенные прокси в `bootstrap/app.php`: `$middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_PROTO)`. Доверяется только схема — ровно то, что нужно, чтобы за Cloudflare `url()` отдавал `https://` и redirect URI совпадал с зарегистрированным в Google; без этого вход ломается с ошибкой, не упоминающей прокси. `X-Forwarded-For` и `X-Forwarded-Host` в доверенные не входят: адрес клиента нигде не используется, а подмена host через запрос в обход Cloudflare исключается на корню
- [ ] T028a Настроить `bootstrap/app.php` целиком за один заход: группа `api` с `auth:sanctum` и `throttle:getid`, отдельная группа для маршрутов MCP, и регистрация `LogApiRequest` (класс появится в T084 — регистрируется по имени). Вместе с T103 это единственное место, где правится `bootstrap/app.php`, и обе задачи лежат в одном шаге: две разные фазы, пишущие этот файл, при исполнении бандлами конфликтуют
- [ ] T029 [P] Создать иерархию доменных исключений в `app/Domain/Sequence/Exceptions/`: `UnknownProject`, `InactiveProject`, `TypeNotEnabled`, `InactiveKeyType`, `UnparsableOrigin`, `EmptyDocumentName` — каждое несёт код из `DomainError.error.code` контракта
- [ ] T030 Отрисовать доменные исключения в JSON формы `DomainError` (contracts/rest-api.yaml) через `withExceptions()->render()` со статусом 422. Отдельно привести к той же форме исключения фреймворка, которые контракт тоже описывает как `DomainError`: `AuthenticationException` → 401 `unauthenticated`, `AccessDeniedHttpException` → 403 `forbidden`, `NotFoundHttpException` → 404 `not_found`, `ThrottleRequestsException` → 429 `rate_limited`
- [ ] T031 [P] Фабрики `database/factories/` для `Project`, `KeyType`, `ProjectKeyType`, `Identifier`
- [ ] T032 [P] Seeder `database/seeders/KeyTypeSeeder.php`: `ADR` → `ADR-{number:04d}`, `spec` → `{number:03d}-{name}`

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


- [ ] T033 [P] [US1] `tests/Feature/Sequence/NextIdTest.php`: первая выдача, `formatted_id` по шаблону, `is_new: true` (FR-001, FR-005)
- [ ] T034 [P] [US1] `tests/Feature/Sequence/IdempotencyTest.php`: повтор той же тройки, повтор с другим регистром и разделителем, отсутствие второй записи в реестре (FR-002, FR-007)
- [ ] T035 [P] [US1] `tests/Feature/Sequence/RejectionTest.php`: незарегистрированный проект, погашенный проект, невключённый тип, погашенный тип, пустая после нормализации тема — каждый со своим `error.code` и нормализованным ключом в теле (FR-010, FR-015, FR-007a, SC-003)
- [ ] T036 [P] [US1] `tests/Feature/Sequence/ListTest.php`: порядок по убыванию, поля перечня, отказ по незарегистрированному проекту (FR-006)
- [ ] T037 [P] [US1] `tests/Feature/Sequence/SeedSequenceTest.php`: при `seed_sequence = 42` первая выдача возвращает 43 (FR-014a)
- [ ] T038 [US1] `tests/Concurrency/ConcurrentIssueTest.php`: 50 процессов через `Process::pool` на одну пару «проект + тип», все со своей меткой старта `--at`, проверка — ровно 50 различных номеров без пропусков; набор не оборачивается в транзакцию и чистит таблицы усечением (SC-001, принцип V)
- [ ] T039 [US1] Команда `app/Console/Commands/IssueIdentifier.php` — точка входа для процессов теста конкурентности: принимает `--at=<unix ms>` и ждёт до этой метки перед вызовом `SequenceIssuer`, печатает выданный номер в stdout. Дочерние процессы запускаются с тем же `APP_ENV=testing` и `DB_DATABASE=getid_test`, что и сам тест, — окружение передаётся в `Process::env()` явно, а не наследуется на удачу. Барьер обязателен: `Process::pool` стартует процессы последовательно, холодный старт Laravel занимает сотни миллисекунд, и без общей метки первый процесс успевает закоммитить транзакцию раньше, чем второй дойдёт до `lockForUpdate()` — тест станет зелёным на заведомо сломанной реализации
- [ ] T095 [US1] `tests/Concurrency/ConcurrentSameNameTest.php`: 10 процессов запрашивают номер с **одной и той же** темой одновременно, с тем же барьером `--at`, что и T038; проверка — все получают один номер, в реестре ровно одна запись, и `project_key_type.last_sequence` сдвинулся ровно на единицу — проигравшие гонку не сожгли номера (SC-002, FR-004a, FR-004b). Отличается от T038, где темы разные: тот проверяет сериализацию счётчика, этот — разрешение столкновения по уникальному индексу
- [ ] T096 [P] [US1] `tests/Feature/Sequence/StorageFailureTest.php`: отказ хранилища в момент выдачи — клиент получает ошибку, номер не выдан, счётчик не сдвинут, повтор после восстановления безопасен (FR-004c)
- [ ] T097 [P] [US1] `tests/Feature/Sequence/ImmutabilityTest.php`: реестр не допускает обновления и удаления записи; `down()` миграции реестра на непустой таблице бросает исключение, а на пустой отрабатывает (FR-004, FR-016)
- [ ] T098 [P] [US1] `tests/Feature/UnauthenticatedAccessTest.php`: каждый маршрут `/api/v1/*` без заголовка авторизации и с отозванным токеном отвергается (FR-020); проект не заводится сам по факту обращения (FR-011)

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

- [ ] T040 [US1] `app/Domain/Sequence/IssuedIdentifier.php` — неизменяемый результат выдачи (номер, форматированный вид, признак новизны, исходная тема)
- [ ] T041 [US1] `app/Domain/Sequence/SequenceIssuer.php`: поиск существующей записи по `(project, type, name_slug)` до транзакции; иначе транзакция с `lockForUpdate()` на строке `project_key_type`, следующий номер `GREATEST(seed_sequence, last_sequence) + 1`, инкремент счётчика, вставка (research.md §R1, FR-003, FR-004a)
- [ ] T042 [US1] Обработать `UniqueConstraintViolationException` **снаружи** `DB::transaction()`, а не внутри: исключение обязано выйти из замыкания, чтобы транзакция откатилась целиком вместе с инкрементом счётчика. Если поймать его внутри и перечитать запись там же, инкремент закоммитится и номер сгорит — пропуск, который запрещает FR-004a. После отката перечитать запись по `name_slug`: нашлась — это штатный повтор, вернуть её с `is_new: false`; не нашлась — столкнулся `sequence_number`, то есть сериализация не сработала: дефект, запись в лог приложения и ошибка сервера (FR-004a, FR-004b). `DB::transaction()` вызывать с числом попыток больше единицы, чтобы редкий deadlock не дошёл до клиента (FR-003)
- [ ] T043 [P] [US1] `app/Http/Requests/Api/NextSequenceRequest.php` и `ListSequenceRequest.php` по схемам contracts/rest-api.yaml
- [ ] T044 [P] [US1] `app/Http/Resources/IssuedIdentifierResource.php` и `IdentifierListResource.php` — форма ответа из контракта, `name` отдаётся исходный, не нормализованный
- [ ] T045 [US1] `app/Http/Controllers/Api/SequenceController.php` — два действия, вся работа делегируется `SequenceIssuer`
- [ ] T046 [US1] Зарегистрировать `POST /api/v1/sequence/next` и `GET /api/v1/sequence/list` в `routes/api.php`

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

- [ ] T047 [P] [US2] `tests/Feature/Admin/ProjectCrudTest.php`: заведение с вычислением ключа из адреса, переименование, гашение, повторный адрес как ошибка валидации (FR-008, FR-012)
- [ ] T048 [P] [US2] `tests/Feature/Admin/KeyTypeCrudTest.php`: заведение, валидация шаблона (нет номера, неизвестный плейсхолдер), гашение (FR-013, FR-013a)
- [ ] T049 [P] [US2] `tests/Feature/Admin/ProjectKeyTypeTest.php`: замена набора типов, `seed_sequence`, запрет `seed_sequence` ниже выданного, запрет включения погашенного типа, продолжение нумерации после выключения и повторного включения (FR-014, FR-014a, FR-014b, Edge Cases)
- [ ] T050 [P] [US2] `tests/Feature/Admin/AuthorizationTest.php`: обычный пользователь получает 403 на каждой административной операции и не получает перечня проектов; 403 приходит и для несуществующего идентификатора, то есть проверка роли срабатывает до поиска сущности, и по коду ответа нельзя узнать, существует ли объект (FR-017)
- [ ] T051 [P] [US2] `tests/Feature/ProjectResolveTest.php`: SSH- и HTTPS-формы дают один ключ, незарегистрированный проект отдаёт `registered: false` и `hint`, неразбираемый адрес — 422, перечень чужих проектов не раскрывается (FR-009, FR-010)

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

- [ ] T052 [P] [US2] `app/Policies/ProjectPolicy.php` и `app/Policies/KeyTypePolicy.php`, регистрация в `AppServiceProvider`
- [ ] T053 [P] [US2] FormRequest'ы в `app/Http/Requests/Api/Admin/`: `StoreProjectRequest`, `UpdateProjectRequest`, `StoreKeyTypeRequest`, `UpdateKeyTypeRequest`, `SetProjectKeyTypesRequest` — правило валидации шаблона опирается на `IdentifierFormat`, правило `seed_sequence` сверяется с `last_sequence`
- [ ] T054 [P] [US2] Ресурсы `app/Http/Resources/ProjectResource.php`, `KeyTypeResource.php`, `EnabledKeyTypeResource.php`
- [ ] T055 [US2] `app/Http/Controllers/Api/Admin/ProjectController.php` — перечень, заведение, изменение
- [ ] T056 [US2] `app/Http/Controllers/Api/Admin/KeyTypeController.php` — перечень, заведение, изменение
- [ ] T057 [US2] `app/Http/Controllers/Api/Admin/ProjectKeyTypeController.php` — полная замена набора типов проекта: тип, пропавший из набора, получает `is_enabled = false`, вернувшийся — `true`; строки связи не удаляются никогда, иначе теряется счётчик (FR-016, data-model.md §project_key_type)
- [ ] T058 [US2] `app/Http/Controllers/Api/ProjectResolveController.php` — нормализация адреса и статус проекта, без раскрытия перечня
- [ ] T059 [US2] Зарегистрировать административные маршруты и `GET /api/v1/projects/resolve` в `routes/api.php`

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

- [ ] T060 [P] [US3] `tests/Feature/Auth/GoogleLoginTest.php`: корпоративный домен проходит, посторонний отвергается и пользователь не создаётся, адрес из `ADMIN_EMAILS` получает роль администратора при первом входе (FR-018, FR-021)
- [ ] T061 [P] [US3] `tests/Feature/Auth/TokenManagementTest.php`: несколько именованных токенов, значение показывается один раз, отзыв одного не трогает остальные (FR-019, FR-019a)
- [ ] T062 [P] [US3] `tests/Feature/Auth/AdminLifecycleTest.php`: снятие роли у последнего администратора отвергается; деактивация сотрудника сносит его токены и сохраняет авторство в реестре (FR-021a, FR-021b)
- [ ] T063 [P] [US3] `tests/Feature/Auth/RateLimitTest.php`: превышение порога даёт 429 с кодом, отличным от отказа по правам (FR-020a)
- [ ] T099 [P] [US3] `tests/Feature/Auth/ProviderOutageTest.php`: при недоступности Google вход людей отвергается, а запросы с уже выданными токенами продолжают обслуживаться — проверка токена не обращается к провайдеру (FR-021c)

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

- [ ] T064 [US3] `config/services.php` — секция `google`; `config/getid.php` — `allowed_email_domain`, `admin_emails`, `api_log_retention_days`
- [ ] T065 [US3] `app/Http/Controllers/Web/GoogleAuthController.php` — редирект и колбэк, проверка домена до создания пользователя, повышение по `ADMIN_EMAILS`
- [ ] T066 [P] [US3] `app/Http/Controllers/Web/TokenController.php` — перечень, создание, отзыв; значение токена кладётся в flash один раз
- [ ] T067 [P] [US3] `app/Actions/DeactivateUser.php` — гашение сотрудника со сносом токенов и проверкой «последний администратор»
- [ ] T101 [P] [US3] Консольные команды управления пользователями — единственный путь для этих операций (FR-021): `app/Console/Commands/SetUserRoleCommand.php` (`user:role {email} {admin|member}`, отказ при снятии роли у последнего администратора — FR-021a) и `app/Console/Commands/DeactivateUserCommand.php` (`user:deactivate {email}`, тонкая обёртка над `App\Actions\DeactivateUser` — FR-021b). Тесты — в `tests/Feature/Auth/AdminLifecycleTest.php` (T062)
- [ ] T068 [P] [US3] Blade: `resources/views/layouts/app.blade.php`, `auth/login.blade.php`, `tokens/index.blade.php`
- [ ] T069 [P] [US3] Blade административных экранов: `admin/projects/index.blade.php`, `admin/key-types/index.blade.php`
- [ ] T070 [US3] Маршруты в `routes/web.php`: вход, кабинет токенов, административные экраны под Gate. Все маршруты именованные (`auth.google.redirect`, `auth.google.callback`, `tokens.index`, `admin.projects.index`, `admin.key-types.index`) — на `tokens.index` опирается проверка схемы ссылок после выкладки в quickstart.md

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

- [ ] T071 [P] [US4] `tests/Mcp/ResolveProjectToolTest.php`: схема инструмента, зарегистрированный и незарегистрированный проект, неразбираемый адрес как `Response::error`
- [ ] T072 [P] [US4] `tests/Mcp/NextIdToolTest.php`: выдача, идемпотентность, отказ с текстом, который начинается с кода причины из REST-контракта (`project_not_registered: …`) и называет ключ и следующий шаг (FR-024)
- [ ] T073 [P] [US4] `tests/Mcp/ListIdentifiersToolTest.php`: порядок и состав перечня
- [ ] T074 [US4] `tests/Mcp/ParityWithRestTest.php`: для каждой пары «tool ↔ endpoint» результат на одних и тех же входных данных совпадает, включая отказы — код причины в MCP совпадает с `error.code` REST для всех четырёх причин из FR-015 (FR-024)
- [ ] T075 [P] [US4] `tests/Mcp/AuthorizationTest.php`: обращение без заголовка авторизации отвергается на маршруте, до инструмента

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

- [ ] T076 [US4] `app/Mcp/Servers/GetIdServer.php` — имя, инструкция сервера, регистрация трёх инструментов
- [ ] T077 [P] [US4] `app/Mcp/Tools/ResolveProjectTool.php` — схема и текст описания из [contracts/mcp-tools.md](./contracts/mcp-tools.md), включая фразу о том, что origin читает клиент (FR-023)
- [ ] T078 [P] [US4] `app/Mcp/Tools/NextIdTool.php` — вызывает `SequenceIssuer`, своей логики выдачи не содержит (принцип VI)
- [ ] T079 [P] [US4] `app/Mcp/Tools/ListIdentifiersTool.php`
- [ ] T080 [US4] Зарегистрировать сервер в `routes/api.php`: `Mcp::web('/mcp', GetIdServer::class)->middleware(['auth:sanctum', 'throttle:getid'])` — тот же именованный limiter, что у REST (T028), отдельного файла маршрутов пакет не заводит (FR-020a, FR-022)

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

- [ ] T081 [P] [US5] `tests/Feature/ApiLoggingTest.php`: состав записи для REST и для MCP — пользователь, токен, endpoint, параметры, код, длительность; сетевой адрес не пишется (FR-025, FR-024a)
- [ ] T082 [P] [US5] `tests/Feature/ApiLoggingFailureTest.php`: при падении записи клиент получает выданный номер, а не ошибку (FR-026)
- [ ] T083 [P] [US5] `tests/Feature/PruneApiLogsTest.php`: чистка сносит записи старше горизонта и не трогает реестр

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

- [ ] T084 [US5] `app/Http/Middleware/LogApiRequest.php` — пользователь и снимок имени токена (`$request->user()->currentAccessToken()->name`, см. data-model.md §api_logs), замер длительности, запись после ответа, перехват исключения записи в лог приложения; сохраняемый `payload` обрезается сверху (4 КБ), чтобы одна крупная посылка не раздувала журнал
- [ ] T085 [US5] Проверить, что `LogApiRequest` уже зарегистрирован на группах `api` и `/mcp` в T028a, и что записи появляются для обеих поверхностей; сам `bootstrap/app.php` здесь не правится
- [ ] T086 [P] [US5] `app/Console/Commands/PruneApiLogs.php` и регистрация в планировщике `routes/console.php` с горизонтом из `config/getid.php`

**Checkpoint**: обращения журналируются, журнал не растёт бесконечно

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

- [ ] T087 Прогнать весь набор в Docker: `make test` — зелёный, включая `tests/Concurrency` (принцип VII, SC-006)
- [ ] T088 [P] Прогнать `vendor/bin/pint` и `vendor/bin/phpstan analyse` до чистого вывода
- [ ] T089 [P] Пройти [quickstart.md](./quickstart.md) целиком на чистом окружении, включая подключение MCP и выдачу номера из сессии ассистента без ручного ввода ключа проекта; расхождения исправить в самом quickstart (SC-004, SC-005)
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

- [ ] T090 [P] Написать `CLAUDE.md` репозитория: непрозрачные решения (счётчик в pivot, нормализация ключа, граница MCP-поверхности) и указатели на живые документы
- [ ] T091 [P] Написать ADR `docs/adr/adr-001-sequence-locking.md` — блокировка строки-счётчика против `SELECT MAX() FOR UPDATE`, с отвергнутыми альтернативами из research.md §R1
- [ ] T092 [P] Написать ADR `docs/adr/adr-002-mcp-surface-boundary.md` — почему административные операции не публикуются в MCP
- [ ] T093 Сократить принципы конституции до строк-указателей на CLAUDE.md и ADR там, где источник появился (Governance конституции)
- [ ] T094 Закрыть пакет: отметить выполненные пункты [checklists/integrity.md](./checklists/integrity.md), обновить статус в [spec.md](./spec.md)
- [ ] T102 Записать в ledger прогнанные гейты: `~/.claude/skills/speckit-gates/scripts/gates.sh record analyze --package specs/001-incremental-id-registry` и то же для `converge` после его прогона. `/speckit-analyze` read-only и сам ничего не фиксирует, поэтому без этой записи гейт остаётся `MISSING`, хотя анализ был выполнен

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

- [ ] S1 (~600K) Steps 1.1, 2.1, 2.2, 2.3, 2.4 — **current**
- [ ] S2 (~600K) Steps 3.1, 3.2, 4.1, 4.2, 5.1
- [ ] S3 (~550K) Steps 5.2, 6.1, 6.2, 7.1, 7.2
- [ ] S4 (~190K) Steps 8.1, 8.2
