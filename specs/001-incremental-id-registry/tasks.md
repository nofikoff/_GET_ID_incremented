---

description: "Task list for incremental id registry"
---

# Tasks: Реестр инкрементальных идентификаторов

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

- [ ] T001 Создать приложение Laravel 13 поверх существующего репозитория: `composer create-project laravel/laravel:^13.0 tmp-app && mv tmp-app/* tmp-app/.* . && rm -rf tmp-app`, сохранив `README.md`, `.gitignore` и `specs/`
- [ ] T002 Зафиксировать PHP 8.3 в `composer.json` (`"php": "^8.3"`) и сверить, что `composer.lock` не тянет пакеты с более низкой границей
- [ ] T003 [P] Написать `docker/php/Dockerfile` (php-fpm 8.3 с `pdo_mysql`, `bcmath`, `mbstring`, `intl`), `docker/nginx/default.conf` с корнем в `public/`
- [ ] T004 [P] Написать `docker-compose.yml`: сервисы `app`, `nginx` (порт 8080), `mysql` 8.0 с томом и healthcheck
- [ ] T005 [P] Написать `Makefile` с целями `up`, `down`, `migrate`, `fresh`, `test`, `test-race`, `shell` — все через `docker compose exec app`
- [ ] T006 Установить зависимости: `composer require laravel/sanctum laravel/socialite laravel/mcp` и опубликовать конфиги Sanctum
- [ ] T007 [P] Установить инструменты качества: `composer require --dev larastan/larastan laravel/pint pestphp/pest`, настроить `phpstan.neon` на уровень 6 и `pint.json`
- [ ] T008 [P] Заполнить `.env.example` ключами `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`, `ADMIN_EMAILS`, `ALLOWED_EMAIL_DOMAIN=cas.ai`, `API_LOG_RETENTION_DAYS=90`
- [ ] T009 Проверить, что `make up && make migrate` поднимает окружение и стандартные миграции Laravel проходят

**Checkpoint**: приложение отвечает на `http://localhost:8080`, тесты запускаются в контейнере

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: схема, модели и три value object, на которые опираются все истории

**⚠️ CRITICAL**: ни одна история не начинается, пока эта фаза не закрыта

### Схема данных

- [ ] T010 Миграция `database/migrations/*_add_role_and_deactivation_to_users_table.php`: `google_id`, `avatar_url`, `role` enum(`member`,`admin`) default `member`, `deactivated_at`, снятие `password` и `email_verified_at`
- [ ] T011 [P] Миграция `database/migrations/*_create_projects_table.php` по [data-model.md](./data-model.md) §projects, включая UNIQUE по `key` с учётом лимита длины индекса InnoDB
- [ ] T012 [P] Миграция `database/migrations/*_create_key_types_table.php` по §key_types
- [ ] T013 Миграция `database/migrations/*_create_project_key_type_table.php` по §project_key_type: `seed_sequence`, `last_sequence`, UNIQUE `(project_id, key_type_id)`
- [ ] T014 Миграция `database/migrations/*_create_identifiers_table.php` по §identifiers: оба UNIQUE-индекса, индекс для перечня по убыванию, `down()` только дропает таблицу и не трогает данные
- [ ] T015 [P] Миграция `database/migrations/*_create_api_logs_table.php` по §api_logs с индексом по `created_at`

### Модели

- [ ] T016 [P] Модель `app/Models/Project.php`: связь `keyTypes()` через `project_key_type` с `withPivot`, scope `active()`
- [ ] T017 [P] Модель `app/Models/KeyType.php` со scope `active()`
- [ ] T018 [P] Модель `app/Models/ProjectKeyType.php` (pivot как полноценная модель — она несёт счётчик)
- [ ] T019 [P] Модель `app/Models/Identifier.php` без `update`/`delete` в публичном интерфейсе
- [ ] T020 [P] Модель `app/Models/ApiLog.php`
- [ ] T021 Дополнить `app/Models/User.php`: `HasApiTokens`, каст `role` в enum `app/Enums/UserRole.php`, метод `isAdmin()`, scope `active()`

### Value objects и их тесты

- [ ] T022 [P] Тест `tests/Unit/ProjectKeyTest.php`: таблица примеров SSH/HTTPS/порт/`.git`/регистр → один ключ, плюс неразбираемые строки (research.md §R5)
- [ ] T023 [P] Тест `tests/Unit/DocumentNameTest.php`: регистр, пробелы, подчёркивания, повторы разделителей, пустой результат (FR-007, FR-007a)
- [ ] T024 [P] Тест `tests/Unit/IdentifierFormatTest.php`: `{number}`, `{number:04d}`, `{name}`, неизвестный плейсхолдер, отсутствие номера, номер шире ширины шаблона (FR-013a, Edge Cases)
- [ ] T025 [P] Реализовать `app/Domain/Project/ProjectKey.php` — разбор без `parse_url` для SCP-формы
- [ ] T026 [P] Реализовать `app/Domain/KeyType/DocumentName.php` — хранит исходную строку и slug
- [ ] T027 [P] Реализовать `app/Domain/KeyType/IdentifierFormat.php` — разбор шаблона и применение, без `sprintf` от чужой строки

### Инфраструктура запроса

- [ ] T028 Настроить `bootstrap/app.php`: группа `api` с `auth:sanctum` и `throttle:60,1` (FR-020a), отдельная группа для маршрутов MCP
- [ ] T029 [P] Создать иерархию доменных исключений в `app/Domain/Sequence/Exceptions/`: `UnknownProject`, `InactiveProject`, `TypeNotEnabled`, `InactiveKeyType`, `UnparsableOrigin`, `EmptyDocumentName` — каждое несёт код из `DomainError.error.code` контракта
- [ ] T030 Отрисовать доменные исключения в JSON формы `DomainError` (contracts/rest-api.yaml) через `bootstrap/app.php` → `withExceptions()->render()`, статус 422
- [ ] T031 [P] Фабрики `database/factories/` для `Project`, `KeyType`, `ProjectKeyType`, `Identifier`
- [ ] T032 [P] Seeder `database/seeders/KeyTypeSeeder.php`: `ADR` → `ADR-{number:04d}`, `spec` → `{number:03d}-{name}`

**Checkpoint**: схема разворачивается, unit-тесты value object зелёные, истории можно вести параллельно

---

## Phase 3: User Story 1 - Выдача номера без гонки (Priority: P1) 🎯 MVP

**Goal**: клиент получает номер, который не будет выдан второй раз; повтор возвращает тот же номер

**Independent Test**: на заполненном фабриками справочнике — два одновременных запроса дают разные
номера, повтор одного из них возвращает прежний

### Тесты (пишутся первыми и падают)

- [ ] T033 [P] [US1] `tests/Feature/Sequence/NextIdTest.php`: первая выдача, `formatted_id` по шаблону, `is_new: true` (FR-001, FR-005)
- [ ] T034 [P] [US1] `tests/Feature/Sequence/IdempotencyTest.php`: повтор той же тройки, повтор с другим регистром и разделителем, отсутствие второй записи в реестре (FR-002, FR-007)
- [ ] T035 [P] [US1] `tests/Feature/Sequence/RejectionTest.php`: незарегистрированный проект, погашенный проект, невключённый тип, погашенный тип, пустая после нормализации тема — каждый со своим `error.code` (FR-010, FR-015, FR-007a)
- [ ] T036 [P] [US1] `tests/Feature/Sequence/ListTest.php`: порядок по убыванию, поля перечня, отказ по незарегистрированному проекту (FR-006)
- [ ] T037 [P] [US1] `tests/Feature/Sequence/SeedSequenceTest.php`: при `seed_sequence = 42` первая выдача возвращает 43 (FR-014a)
- [ ] T038 [US1] `tests/Concurrency/ConcurrentIssueTest.php`: 50 процессов через `Process::pool` на одну пару «проект + тип», проверка — ровно 50 различных номеров без пропусков; набор не оборачивается в транзакцию и чистит таблицы усечением (SC-001, принцип V)
- [ ] T039 [US1] Команда `app/Console/Commands/IssueIdentifier.php` — точка входа для процессов теста конкурентности, печатает выданный номер в stdout

### Реализация

- [ ] T040 [US1] `app/Domain/Sequence/IssuedIdentifier.php` — неизменяемый результат выдачи (номер, форматированный вид, признак новизны, исходная тема)
- [ ] T041 [US1] `app/Domain/Sequence/SequenceIssuer.php`: поиск существующей записи по `(project, type, name_slug)` до транзакции; иначе транзакция с `lockForUpdate()` на строке `project_key_type`, следующий номер `GREATEST(seed_sequence, last_sequence) + 1`, инкремент счётчика, вставка (research.md §R1, FR-003, FR-004a)
- [ ] T042 [US1] Обработать в `SequenceIssuer` нарушение UNIQUE по `(project, type, name_slug)` как повтор — перечитать и вернуть существующую запись; нарушение по `(project, type, sequence_number)` пробросить как дефект с записью в лог приложения (FR-004b)
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

### Тесты

- [ ] T047 [P] [US2] `tests/Feature/Admin/ProjectCrudTest.php`: заведение с вычислением ключа из адреса, переименование, гашение, повторный адрес как ошибка валидации (FR-008, FR-012)
- [ ] T048 [P] [US2] `tests/Feature/Admin/KeyTypeCrudTest.php`: заведение, валидация шаблона (нет номера, неизвестный плейсхолдер), гашение (FR-013, FR-013a)
- [ ] T049 [P] [US2] `tests/Feature/Admin/ProjectKeyTypeTest.php`: замена набора типов, `seed_sequence`, запрет `seed_sequence` ниже выданного, запрет включения погашенного типа, продолжение нумерации после выключения и повторного включения (FR-014, FR-014a, FR-014b, Edge Cases)
- [ ] T050 [P] [US2] `tests/Feature/Admin/AuthorizationTest.php`: обычный пользователь получает 403 на каждой административной операции и не получает перечня проектов (FR-017)
- [ ] T051 [P] [US2] `tests/Feature/ProjectResolveTest.php`: SSH- и HTTPS-формы дают один ключ, незарегистрированный проект отдаёт `registered: false` и `hint`, неразбираемый адрес — 422, перечень чужих проектов не раскрывается (FR-009, FR-010)

### Реализация

- [ ] T052 [P] [US2] `app/Policies/ProjectPolicy.php` и `app/Policies/KeyTypePolicy.php`, регистрация в `AppServiceProvider`
- [ ] T053 [P] [US2] FormRequest'ы в `app/Http/Requests/Api/Admin/`: `StoreProjectRequest`, `UpdateProjectRequest`, `StoreKeyTypeRequest`, `UpdateKeyTypeRequest`, `SetProjectKeyTypesRequest` — правило валидации шаблона опирается на `IdentifierFormat`, правило `seed_sequence` сверяется с `last_sequence`
- [ ] T054 [P] [US2] Ресурсы `app/Http/Resources/ProjectResource.php`, `KeyTypeResource.php`, `EnabledKeyTypeResource.php`
- [ ] T055 [US2] `app/Http/Controllers/Api/Admin/ProjectController.php` — перечень, заведение, изменение
- [ ] T056 [US2] `app/Http/Controllers/Api/Admin/KeyTypeController.php` — перечень, заведение, изменение
- [ ] T057 [US2] `app/Http/Controllers/Api/Admin/ProjectKeyTypeController.php` — полная замена набора типов проекта с сохранением счётчиков
- [ ] T058 [US2] `app/Http/Controllers/Api/ProjectResolveController.php` — нормализация адреса и статус проекта, без раскрытия перечня
- [ ] T059 [US2] Зарегистрировать административные маршруты и `GET /api/v1/projects/resolve` в `routes/api.php`

**Checkpoint**: справочник ведётся администратором, реестр закрыт для остальных

---

## Phase 5: User Story 3 - Вход сотрудника и токены (Priority: P3)

**Goal**: сотрудник домена `@cas.ai` входит через Google и управляет своими токенами

**Independent Test**: вход корпоративным аккаунтом создаёт пользователя и даёт создать токен; вход
посторонним доменом отвергается без создания учётной записи

### Тесты

- [ ] T060 [P] [US3] `tests/Feature/Auth/GoogleLoginTest.php`: корпоративный домен проходит, посторонний отвергается и пользователь не создаётся, адрес из `ADMIN_EMAILS` получает роль администратора при первом входе (FR-018, FR-021)
- [ ] T061 [P] [US3] `tests/Feature/Auth/TokenManagementTest.php`: несколько именованных токенов, значение показывается один раз, отзыв одного не трогает остальные (FR-019, FR-019a)
- [ ] T062 [P] [US3] `tests/Feature/Auth/AdminLifecycleTest.php`: снятие роли у последнего администратора отвергается; деактивация сотрудника сносит его токены и сохраняет авторство в реестре (FR-021a, FR-021b)
- [ ] T063 [P] [US3] `tests/Feature/Auth/RateLimitTest.php`: превышение порога даёт 429 с кодом, отличным от отказа по правам (FR-020a)

### Реализация

- [ ] T064 [US3] `config/services.php` — секция `google`; `config/getid.php` — `allowed_email_domain`, `admin_emails`, `api_log_retention_days`
- [ ] T065 [US3] `app/Http/Controllers/Web/GoogleAuthController.php` — редирект и колбэк, проверка домена до создания пользователя, повышение по `ADMIN_EMAILS`
- [ ] T066 [P] [US3] `app/Http/Controllers/Web/TokenController.php` — перечень, создание, отзыв; значение токена кладётся в flash один раз
- [ ] T067 [P] [US3] `app/Actions/DeactivateUser.php` — гашение сотрудника со сносом токенов и проверкой «последний администратор»
- [ ] T068 [P] [US3] Blade: `resources/views/layouts/app.blade.php`, `auth/login.blade.php`, `tokens/index.blade.php`
- [ ] T069 [P] [US3] Blade административных экранов: `admin/projects/index.blade.php`, `admin/key-types/index.blade.php`
- [ ] T070 [US3] Маршруты в `routes/web.php`: вход, кабинет токенов, административные экраны под Gate

**Checkpoint**: сервисом можно пользоваться без ручной правки базы

---

## Phase 6: User Story 4 - AI-ассистент через MCP (Priority: P4)

**Goal**: ассистент получает номер, не спрашивая человека о ключе проекта

**Independent Test**: подключить сервер клиентом, вызвать `resolve_project` и `next_id` — результат
совпадает с REST на тех же данных

### Тесты

- [ ] T071 [P] [US4] `tests/Mcp/ResolveProjectToolTest.php`: схема инструмента, зарегистрированный и незарегистрированный проект, неразбираемый адрес как `Response::error`
- [ ] T072 [P] [US4] `tests/Mcp/NextIdToolTest.php`: выдача, идемпотентность, отказ с текстом, называющим ключ и следующий шаг
- [ ] T073 [P] [US4] `tests/Mcp/ListIdentifiersToolTest.php`: порядок и состав перечня
- [ ] T074 [US4] `tests/Mcp/ParityWithRestTest.php`: для каждой пары «tool ↔ endpoint» результат на одних и тех же входных данных совпадает (FR-024)
- [ ] T075 [P] [US4] `tests/Mcp/AuthorizationTest.php`: обращение без заголовка авторизации отвергается на маршруте, до инструмента

### Реализация

- [ ] T076 [US4] `app/Mcp/Servers/GetIdServer.php` — имя, инструкция сервера, регистрация трёх инструментов
- [ ] T077 [P] [US4] `app/Mcp/Tools/ResolveProjectTool.php` — схема и текст описания из [contracts/mcp-tools.md](./contracts/mcp-tools.md), включая фразу о том, что origin читает клиент (FR-023)
- [ ] T078 [P] [US4] `app/Mcp/Tools/NextIdTool.php` — вызывает `SequenceIssuer`, своей логики выдачи не содержит (принцип VI)
- [ ] T079 [P] [US4] `app/Mcp/Tools/ListIdentifiersTool.php`
- [ ] T080 [US4] `routes/ai.php`: `Mcp::web('/mcp', GetIdServer::class)->middleware(['auth:sanctum', 'throttle:60,1'])`

**Checkpoint**: `claude mcp add --transport http` подключает сервер, номер выдаётся из сессии ассистента

---

## Phase 7: User Story 5 - Журнал обращений (Priority: P5)

**Goal**: восстановимо, кто и когда запрашивал номера

**Independent Test**: обращение к API и к MCP оставляет запись с пользователем, параметрами, кодом
ответа и длительностью; сбой записи не отменяет выдачу

### Тесты

- [ ] T081 [P] [US5] `tests/Feature/ApiLoggingTest.php`: состав записи для REST и для MCP (FR-025, FR-024a)
- [ ] T082 [P] [US5] `tests/Feature/ApiLoggingFailureTest.php`: при падении записи клиент получает выданный номер, а не ошибку (FR-026)
- [ ] T083 [P] [US5] `tests/Feature/PruneApiLogsTest.php`: чистка сносит записи старше горизонта и не трогает реестр

### Реализация

- [ ] T084 [US5] `app/Http/Middleware/LogApiRequest.php` — замер длительности, запись после ответа, перехват исключения записи в лог приложения
- [ ] T085 [US5] Повесить middleware на группы `api` и маршрут MCP в `bootstrap/app.php`
- [ ] T086 [P] [US5] `app/Console/Commands/PruneApiLogs.php` и регистрация в планировщике `routes/console.php` с горизонтом из `config/getid.php`

**Checkpoint**: обращения журналируются, журнал не растёт бесконечно

---

## Phase 8: Polish & Cross-Cutting Concerns

- [ ] T087 Прогнать весь набор в Docker: `make test` — зелёный, включая `tests/Concurrency` (принцип VII)
- [ ] T088 [P] Прогнать `vendor/bin/pint` и `vendor/bin/phpstan analyse` до чистого вывода
- [ ] T089 [P] Пройти [quickstart.md](./quickstart.md) целиком на чистом окружении и исправить расхождения в самом quickstart
- [ ] T090 [P] Написать `CLAUDE.md` репозитория: непрозрачные решения (счётчик в pivot, нормализация ключа, граница MCP-поверхности) и указатели на живые документы
- [ ] T091 [P] Написать ADR `docs/adr/adr-001-sequence-locking.md` — блокировка строки-счётчика против `SELECT MAX() FOR UPDATE`, с отвергнутыми альтернативами из research.md §R1
- [ ] T092 [P] Написать ADR `docs/adr/adr-002-mcp-surface-boundary.md` — почему административные операции не публикуются в MCP
- [ ] T093 Сократить принципы конституции до строк-указателей на CLAUDE.md и ADR там, где источник появился (Governance конституции)
- [ ] T094 Закрыть пакет: отметить выполненные пункты [checklists/integrity.md](./checklists/integrity.md), обновить статус в [spec.md](./spec.md)

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

- **Phase 1**: T003, T004, T005 (docker и Makefile), T007, T008 — разные файлы
- **Phase 2**: миграции T011, T012, T015 параллельны между собой; T013 и T014 после T011 и T012
  из-за внешних ключей. Модели T016–T020 параллельны. Тесты T022–T024 и реализации T025–T027
  параллельны попарно
- **Phase 3**: тесты T033–T037 параллельны; T038 и T039 идут вместе и после T041
- **Phase 4**: тесты T047–T051 параллельны; T052–T054 параллельны, контроллеры T055–T058 — после них
- **Phase 6**: инструменты T077–T079 параллельны после T076
- **Phase 8**: T088–T092 параллельны

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
