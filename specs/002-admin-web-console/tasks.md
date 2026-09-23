---

description: "Task list for admin web console"
---

# Tasks: Веб-консоль администратора

**Input**: Design documents from `/specs/002-admin-web-console/`

**Prerequisites**: [plan.md](./plan.md), [spec.md](./spec.md), [research.md](./research.md),
[data-model.md](./data-model.md), [contracts/web-console.md](./contracts/web-console.md)

**Tests**: включены и не опциональны — конституция требует feature-теста на успех и отказ для каждой
точки входа (§Порядок работы) и прогона в Docker до готовности (принцип VII).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: можно выполнять параллельно (разные файлы, нет зависимости от незакрытых задач)
- **[Story]**: US1–US5 из spec.md

## Path Conventions

Раскладка Laravel в корне репозитория, как в пакете 001. Новые каталоги — [plan.md](./plan.md)
§Project Structure.

---

## Phase 1: Foundational (Blocking Prerequisites)

**Purpose**: общий прикладной слой, на который встают все формы; REST переходит на него без смены поведения

**⚠️ CRITICAL**: ни одна история не начинается, пока шаг 1.1 не закрыт и `make test` не зелёный без правки ожиданий REST и MCP

### Step 1.1: Общий слой и перенос REST на него

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

- [ ] T001 Наборы правил в `app/Http/Validation/`: `ProjectRules::store()`, `::update()`, `KeyTypeRules::store()`, `::update()`, `ProjectKeyTypeRules::set()` — содержимое переносится из текущих `rules()` запросов `app/Http/Requests/Api/Admin/*` дословно, включая комментарии о collation и `bail` (research.md R1)
- [ ] T002 API-запросы `app/Http/Requests/Api/Admin/*` возвращают наборы из T001; `ClosedBodyRequest`, `#[MinProperties]`, `authorize()` и `types()` не меняются
- [ ] T003 [P] Actions в `app/Actions/Registry/`: `CreateProject` (ключ через `ProjectKey::fromOrigin`, автор, `save()`), `UpdateProject`, `CreateKeyType`, `UpdateKeyType`. Actions принимают уже провалидированные данные и администратора; гонку на UNIQUE обрабатывает вызывающая сторона через `RethrowsUniqueConflictAsValidation`, потому что повторная валидация идёт правилами запроса (research.md R8)
- [ ] T004 След изменений (FR-019, research.md R5): actions и `EnabledKeyTypes`-вызов пишут `Log::info('registry change', [...])` с `admin_id`, `admin_email`, `operation`, `entity`, `entity_id`, `changes` «было → стало»; только при фактическом изменении; сбой записи ловится и уходит в `report()`, изменение не откатывается. Для `set_key_types` — по изменившимся типам (data-model.md §След изменения). Общий код — один класс `app/Actions/Registry/RegistryChangeLog.php`
- [ ] T005 API-контроллеры `app/Http/Controllers/Api/Admin/ProjectController.php`, `KeyTypeController.php`, `ProjectKeyTypeController.php` вызывают actions из T003 и запись следа T004; ответы, коды и тексты не меняются
- [ ] T006 Группа `/admin` в `routes/web.php` закрывается `EnsureAdministrator` вместо `can:administer` (research.md R2); существующие маршруты `admin.projects.index` и `admin.key-types.index` сохраняют имена
- [ ] T007 [P] Тест `tests/Feature/Admin/RegistryChangeLogTest.php`: изменение через REST пишет запись следа с «было → стало»; неизменившее обновление не пишет; сбой канала логов не откатывает изменение (FR-019)
- [ ] T008 Весь набор `make test` зелёный без правки ожиданий существующих REST- и MCP-тестов (SC-003)

**Checkpoint**: REST работает через actions, след пишется, веб-группа закрыта `EnsureAdministrator`

---

## Phase 2: User Story 1 + User Story 5 - Проект в браузере, закрыто от сотрудников (Priority: P1) 🎯 MVP

**Goal**: администратор заводит проект, меняет его и набор типов без терминала; сотрудник без роли не получает ничего

**Independent Test**: на пустом справочнике проект заводится формой, `ADR` включается с seed 12, следующий номер на карточке — 13; сотрудник получает 403 на каждом маршруте, в том числе на несуществующий id

### Step 2.1: Тесты консоли проектов и доступа

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
  test_quick: "docker compose exec -T app php artisan test tests/Feature/Admin/Web"
  test_full: "make test"
tier: standard
-->

Тесты пишутся первыми и падают до Step 2.2.

- [ ] T009 [P] [US1] `tests/Feature/Admin/Web/ProjectFormTest.php`: заведение с выводом ключа, повтор репозитория в другой форме и неразбираемый адрес — redirect back с ошибкой под `repo_url` и `old()`, тот же текст, что в REST; изменение имени и описания; `repo_url` в форме изменения не принимается; погашение и возврат (FR-001–FR-005)
- [ ] T010 [P] [US1] `tests/Feature/Admin/Web/ProjectKeyTypeFormTest.php`: включение `ADR` с seed 12 и `spec` без seed; пустое поле seed не меняет текущий; seed ниже выданного — ошибка под `types.ADR.seed_sequence`, набор прежний; снятая отметка выключает пару без удаления строки; погашенный тип — ошибка под `types.<code>.enabled`, в том числе при гашении между открытием формы и отправкой; включённая пара погашенного типа выключается сохранением (FR-006–FR-010, Edge Cases)
- [ ] T011 [P] [US1] `tests/Feature/Admin/Web/ProjectCreateRaceTest.php`: конкурирующая вставка того же ключа после валидации (событие `Project::creating`) даёт redirect back с ошибкой под `repo_url`, а не 500 (Edge Cases, research.md R8)
- [ ] T012 [P] [US5] `tests/Feature/Admin/Web/ConsoleAccessTest.php`: для каждого маршрута `contracts/web-console.md` сотрудник без роли получает 403, для маршрутов с id — одинаково для существующего и несуществующего; гость — redirect на `login`; администратор, у которого сняли роль, получает 403 со следующего запроса той же сессии; ни одна форма без CSRF-токена не меняет данные (FR-016, FR-017)
- [ ] T013 [P] [US1] `tests/Feature/Admin/Web/ParityWithRestTest.php`: для каждой операции административного REST тот же вход через консоль даёт тот же результат в базе и тот же текст отказа (SC-002)

### Step 2.2: Формы проектов и типов проекта

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
  test_quick: "docker compose exec -T app php artisan test tests/Feature/Admin/Web"
  test_full: "make test"
tier: strong
-->

- [ ] T014 [US1] Веб-запросы `app/Http/Requests/Web/Admin/StoreProjectRequest.php`, `UpdateProjectRequest.php` на наборах T001; `authorize()` — те же Policy, что у API
- [ ] T015 [US1] `app/Http/Requests/Web/Admin/SetProjectKeyTypesRequest.php`: форма `types[<code>][enabled|seed_sequence]` приводится к тому же `list<array{code, seed_sequence|null}>`, что у API; правила — `ProjectKeyTypeRules::set()`, применённые к приведённому набору; ошибки адресуются полям формы по коду типа (research.md R3). Механизм один на весь веб-слой: запрос держит карту `index → code` приведённого списка, `failedValidation()` переписывает ключи `types.<index>.code` → `types.<code>.enabled` и `types.<index>.seed_sequence` → `types.<code>.seed_sequence`; тексты сообщений не меняются
- [ ] T016 [US1] `app/Http/Controllers/Web/Admin/ProjectController.php`: `index`, `create`, `store`, `show`, `update` на actions T003; гонка на UNIQUE — `RethrowsUniqueConflictAsValidation`; redirect с flash-сообщением
- [ ] T017 [US1] `app/Http/Controllers/Web/Admin/ProjectKeyTypeController.php`: вызывает `EnabledKeyTypes::replace()`, `SeedBelowIssued` и `RetiredKeyType` переводит в ошибки полей `types.<code>.seed_sequence` / `types.<code>.enabled`; код берётся по `$refused->position` из того же списка, что передан в `replace()`, через ту же карту `index → code`, что в T015
- [ ] T018 [P] [US1] Шаблоны `resources/views/admin/projects/{index,create,show}.blade.php` и partials `resources/views/admin/partials/` (поле с ошибкой, flash): список со ссылкой «Новый проект»; карточка — реквизиты и форма изменения, форма типов со всеми активными типами, `last_sequence` и следующим номером, строки включённых пар погашенных типов с пометкой (FR-005, FR-006, Edge Cases); текст «заводятся через API» убирается
- [ ] T019 [US1] Подтверждения (research.md R4): `onsubmit="return confirm(...)"` на погашении проекта; инлайн-скрипт формы типов перечисляет снимаемые включённые пары, включая пары погашенных типов, и спрашивает подтверждение, если их больше нуля (FR-004, FR-009)
- [ ] T020 [US1] Маршруты в `routes/web.php` по `contracts/web-console.md` внутри группы T006; ссылки меню в `resources/views/layouts/app.blade.php` не меняются

**Checkpoint**: MVP — проект и его типы ведутся в браузере, сотрудник закрыт

---

## Phase 3: User Story 2 - Справочник типов (Priority: P2)

**Goal**: администратор заводит и правит типы ключей в браузере

**Independent Test**: тип с шаблоном без номера отвергается ошибкой под шаблоном; правильный тип появляется в списке и доступен на карточке проекта

### Step 3.1: Формы типов ключей

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
  test_quick: "docker compose exec -T app php artisan test tests/Feature/Admin/Web"
  test_full: "make test"
tier: standard
-->

- [ ] T021 [P] [US2] `tests/Feature/Admin/Web/KeyTypeFormTest.php`: заведение; шаблон без номера и с неизвестным плейсхолдером — ошибка под `format_template`; код, отличающийся только регистром, — ошибка под `code`; код в форме изменения не принимается; правка шаблона не меняет `formatted_id` выданных номеров; погашение и возврат; гонка на UNIQUE кода — ошибка под `code` (FR-011, FR-012)
- [ ] T022 [US2] Веб-запросы `app/Http/Requests/Web/Admin/StoreKeyTypeRequest.php`, `UpdateKeyTypeRequest.php` на наборах T001
- [ ] T023 [US2] `app/Http/Controllers/Web/Admin/KeyTypeController.php`: `index`, `create`, `store`, `edit`, `update` на actions T003
- [ ] T024 [P] [US2] Шаблоны `resources/views/admin/key-types/{index,create,edit}.blade.php`; подтверждение погашения типа (FR-011); текст «заводятся через API» в `index` убирается, как в T018
- [ ] T025 [US2] Маршруты типов в `routes/web.php` по `contracts/web-console.md`

**Checkpoint**: справочник типов ведётся в браузере

---

## Phase 4: User Story 3 - Выданные номера (Priority: P3)

**Goal**: карточка проекта показывает выданные номера по каждой паре

**Independent Test**: после трёх выдач `ADR` карточка показывает три строки от новой к старой с автором и датой

### Step 4.1: Выданные номера на карточке проекта

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
  test_quick: "docker compose exec -T app php artisan test tests/Feature/Admin/Web"
  test_full: "make test"
tier: standard
-->

- [ ] T026 [P] [US3] `tests/Feature/Admin/Web/IssuedIdentifiersTest.php`: номера пары от новых к старым с `formatted_id`, первой формулировкой, автором и датой; 60 номеров — две страницы по 50 с параметром `page_<code>`, листание одной пары не сдвигает другую; номера выключенной пары видны; на карточке нет форм изменения реестра; число запросов к базе не растёт с числом строк (FR-013, research.md R6)
- [ ] T027 [US3] `app/Queries/IssuedIdentifiers.php`: `forPair(ProjectKeyType, int $perPage = 50)`, `LengthAwarePaginator` с `pageName` `page_<code>`, `with('creator')`
- [ ] T028 [US3] Блок выданных номеров в `resources/views/admin/projects/show.blade.php` по каждой паре проекта, включая выключенные; данные — из `ProjectController::show`

**Checkpoint**: выданное видно без REST

---

## Phase 5: User Story 4 - Журнал обращений (Priority: P4)

**Goal**: администратор фильтрует журнал по сотруднику, датам и поверхности

**Independent Test**: фильтр по одному сотруднику за сегодня показывает только его записи; та же ссылка в новой вкладке даёт тот же результат

### Step 5.1: Экран журнала

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
  test_quick: "docker compose exec -T app php artisan test tests/Feature/Admin/Web"
  test_full: "make test"
tier: standard
-->

- [ ] T029 [P] [US4] `tests/Feature/Admin/Web/ApiLogScreenTest.php`: записи от новых к старым с полями FR-014; фильтр по сотруднику, по дню (обе границы включительно, часовой пояс сервиса), по поверхности `rest`/`mcp` по префиксу `endpoint`; фильтры вместе; ссылки пагинации сохраняют фильтр; деактивированный сотрудник есть в списке фильтра; `to` раньше `from` и несуществующий `user_id` — ошибки под полями; пустой результат — «записей нет» (FR-014, FR-015, Edge Cases)
- [ ] T030 [US4] `app/Queries/ApiLogQuery.php`: фильтры `user_id`, `from`, `to`, `surface`; `created_at desc`; 50 на страницу; `withQueryString()` (research.md R7)
- [ ] T031 [US4] `app/Http/Requests/Web/Admin/ApiLogFilterRequest.php` (GET-фильтр: даты, `to` не раньше `from` с текстом из contracts/web-console.md, `exists` для `user_id`, `surface` из двух значений) и `app/Http/Controllers/Web/Admin/ApiLogController.php`
- [ ] T032 [P] [US4] `resources/views/admin/logs/index.blade.php`: GET-форма фильтра, таблица, раскрытие `payload` через `<details>`; пункт «Журнал» в меню `resources/views/layouts/app.blade.php`; маршрут `admin.logs.index` в `routes/web.php`

**Checkpoint**: журнал разбирается в браузере

---

## Phase 6: Polish & Cross-Cutting Concerns

### Step 6.1: Прогон качества

<!-- plan-meta:
allowed_paths:
  - "app/**"
  - "bootstrap/**"
  - "config/**"
  - "database/**"
  - "routes/**"
  - "resources/**"
  - "tests/**"
  - "specs/002-admin-web-console/quickstart.md"
gate_commands:
  lint: "docker compose exec -T app ./vendor/bin/pint --test"
  type: "docker compose exec -T app ./vendor/bin/phpstan analyse --no-progress"
  test_full: "make test"
tier: standard
-->

- [ ] T033 Прогнать `make test` в Docker — зелёный, включая `tests/Concurrency` и неизменённые тесты REST и MCP (принцип VII, SC-003)
- [ ] T034 [P] `vendor/bin/pint` и `vendor/bin/phpstan analyse` до чистого вывода
- [ ] T035 [P] Пройти [quickstart.md](./quickstart.md) на локальном стеке; вход через Google заменить сессией администратора; расхождения исправить в самом quickstart

### Step 6.2: Документация

<!-- plan-meta:
allowed_paths:
  - "docs/**"
  - "CLAUDE.md"
  - "README.md"
  - "specs/002-admin-web-console/**"
  - "specs/001-incremental-id-registry/quickstart.md"
gate_commands:
  test_full: "make test"
tier: standard
-->

- [ ] T036 [P] ADR-002 `docs/adr/adr-002-mcp-surface-boundary.md`: следствие «Веб-экраны администратора справочник только показывают…» заменить на «Справочник меняет человек через REST `/api/v1/admin/*` или веб-консоль `/admin`; в MCP административных инструментов нет». Ветка не слита, поэтому ADR правится на месте, а не строкой Correction
- [ ] T037 [P] `CLAUDE.md`: где живут правила справочника для трёх транспортов (`app/Http/Validation/`, `app/Actions/Registry/`), почему веб-группа закрыта `EnsureAdministrator`, а не `can:` — указателями на research.md, без пересказа
- [ ] T038 [P] `specs/001-incremental-id-registry/quickstart.md` §5: заведение проекта — сначала консоль `/admin/projects`, curl остаётся альтернативой
- [ ] T039 Закрыть пакет: `spec.md` статус, проверить отметки `checklists/`

---

## Dependencies & Execution Order

```text
Phase 1 (общий слой) ── блокирует всё
   ├─> Phase 2 (US1 + US5, P1) ── MVP
   │      └─> Phase 3 (US2) ── форма типов переиспользует partials Phase 2
   ├─> Phase 4 (US3) ── встраивается в карточку проекта Phase 2
   └─> Phase 5 (US4) ── независим от Phase 2–4 по коду
Phase 6 после всех историй
```

## Parallel Opportunities

- Phase 1: T003 и T007 параллельны после T001
- Phase 2: тесты T009–T013 параллельны; T018 параллелен контроллерам
- Phase 5 независима от Phase 3–4

## Implementation Strategy

**MVP — Phase 1 + Phase 2.** Администратор заводит проекты и включает типы без терминала, сотрудник
закрыт. Дальше типы (Phase 3), выданные номера (Phase 4), журнал (Phase 5). Размер — одна-две
сессии оркестрации: исполнитель `/orchestrate-plan`.
