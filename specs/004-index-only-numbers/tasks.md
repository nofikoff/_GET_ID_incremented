---
description: "Tasks: сервис выдаёт только номер"
---

# Tasks: Сервис выдаёт только номер

**Input**: `specs/004-index-only-numbers/` — spec.md, plan.md, research.md, data-model.md, contracts/changes.md

**Tests**: правка ожиданий существующих тестов — часть каждой задачи; новые тесты — там, где появляется новое поведение (отказ на поле шаблона, отсутствие поля в ответе, тексты справки и описания).

**Gate**: pint, phpstan, `make test` — после каждой фазы. Исполнитель — `/speckit-implement`.

## Phase 1: Foundational — хранение и домен

- [X] T001 Миграция `database/migrations/2026_09_23_200000_drop_format_columns.php` по research.md R1; `tests/Feature/Database/RegistrySchemaTest.php` — колонок нет.
- [X] T002 Удалить `format_template` из `app/Models/KeyType.php`, `database/seeders/KeyTypeSeeder.php`, `database/factories/KeyTypeFactory.php`; `formatted_id` из `app/Models/Identifier.php`, `database/factories/IdentifierFactory.php`; тесты `tests/Feature/Database/{FactoriesTest,KeyTypeSeederTest}.php`, `tests/Feature/Models/*`, `tests/Pest.php` (`enabledPair`).
- [X] T003 Домен: удалить `app/Domain/KeyType/{IdentifierFormat,InvalidFormatTemplate}.php`, `app/Rules/FormatTemplate.php`, `tests/Unit/IdentifierFormatTest.php`, `tests/Feature/Sequence/FormattedIdTest.php`; `IssuedIdentifier`, `WithdrawnIdentifier` без `formattedId`; `SequenceIssuer`, `SequenceWithdrawer` без форматирования; `NotTheLastIdentifier::tail(string $code, int $number)` — «сейчас это ADR 34» (research.md R4); `RegistryChangeLog::withdrawn()` — `sequence_number` вместо `identifier`; `app/Console/Commands/IssueIdentifier.php` без поля. Тесты `tests/Feature/Sequence/*`, `tests/Concurrency/*`, `tests/Feature/Admin/RegistryChangeLogTest.php`.

## Phase 2: User Story 1 — ответ несёт только номер (P1)

- [X] T004 [US1] `app/Http/Resources/{IssuedIdentifier,IdentifierList}Resource.php` без `formatted_id`; тесты `tests/Feature/Sequence/{NextIdTest,ListTest,IdempotencyTest}.php`, `tests/Mcp/*` — поля нет в ответе REST и MCP (SC-001).
- [X] T005 [US1] Описание `next_id` (`app/Mcp/Tools/NextIdTool.php`) и instructions (`app/Mcp/Servers/GetIdServer.php`) — правило research.md R2; тест в `tests/Mcp/GetIdServerTest.php` / `NextIdToolTest.php` на `adr-043-<slug>.md` и `specs/043-<slug>/`.
- [X] T006 [US1] Блок справки `resources/views/help.blade.php` (поверх незакоммиченных правок автора) — правило R2 вместо «formatted_id as is»; `tests/Feature/HelpPageTest.php`.

## Phase 3: User Story 2 — тип без шаблона (P1)

- [X] T007 [US2] `app/Http/Validation/KeyTypeRules.php`, `app/Http/Resources/KeyTypeResource.php` без поля; тест: тело с `format_template` — 422 неизвестное поле (FR-006); `tests/Feature/Admin/{KeyTypeCrudTest,AuthorizationTest,ProjectKeyTypeTest}.php`, `tests/Feature/Http/ContractBodyTest.php`, `tests/Feature/ProjectResolveTest.php`.
- [X] T008 [US2] `resources/views/admin/key-types/{create,edit,index}.blade.php` без поля и подсказки; `tests/Feature/Admin/Web/{KeyTypeFormTest,ParityWithRestTest,ConsoleAccessTest}.php`, `tests/Feature/Auth/WebAccessTest.php`.

## Phase 4: User Story 3 — карточка проекта (P2)

- [X] T009 [US3] `resources/views/admin/projects/show.blade.php`: колонки «Тип | Номер | Ключ запроса | Автор | Выдан» (ключ запроса — тема, переданная в `next_id`), без «Идентификатор»; подтверждение «Удалить ADR 33?»; `IdentifierController` flash «Номер ADR 33 удалён…»; тесты `tests/Feature/Admin/Web/{IssuedIdentifiersTest,WithdrawIdentifierTest,ProjectKeyTypeFormTest}.php`, `tests/Feature/ApiLoggingFailureTest.php`.

## Phase 5: Контракт и документы

- [X] T010 ADR через get-id (research.md R5) — «сервис выдаёт только индекс».
- [X] T011 Переписать по spec FR-010: spec 001 (FR-005, FR-013, FR-013a, сценарии, Edge Cases, сущность), spec 002 (US2, FR-011, FR-012, FR-013, FR-020), spec 003 (FR-002, FR-003, FR-008, сценарии), контракты `rest-api.yaml`, `mcp-tools.md`, `web-console.md` 002 и 003, `specs/001-…/data-model.md`, `specs/001-…/quickstart.md`, `specs/002-…/data-model.md`, `specs/003-…/research.md` и `data-model.md`, `CLAUDE.md` (строки про `formatted_id`; строка «ещё не развёрнуто» уже переписана).

## Phase 6: Закрытие

- [X] T012 `make test`, pint, phpstan зелёные; `grep -rn "formatted_id\|format_template" app resources database tests` находит только три миграции: исходные `2026_09_23_100001_create_key_types_table.php`, `2026_09_23_100003_create_identifiers_table.php` (не правятся — приложение выложено) и новую `drop_format_columns`.
- [X] T013 Закрыть пакет: отметить задачи, записать расхождения с планом.

## Dependencies

Phase 1 → все. Phase 2–4 независимы друг от друга. Phase 5 — после T010. Phase 6 последней.

## Что разошлось с планом (2026-09-23)

- Gate прогонялся один раз в конце, а не после каждой фазы: фазы 1–4 правят одни и те же тесты.
- `WithdrawnIdentifier` получил поле `type` (код типа) вместо удалённого `formattedId`: flash «Номер
  ADR 33 удалён» строится из результата домена, как раньше. `NotTheLastIdentifier` несёт `type` и
  `lastSequenceNumber`; код типа `SequenceWithdrawer` читает после блокировки строки пары, чтобы не
  создать read view раньше неё (spec 003, research.md R2).
- T010: номер ADR не запрашивался — 4 выдан get-id до реализации, файл
  `docs/adr/adr-004-index-only-numbers.md`.
- T012: grep в дополнение к `app resources database tests` прошёл по `routes config` и по
  `formattedId|IdentifierFormat|FormatTemplate`. В `database` остались только две исходные миграции и
  `drop_format_columns`; в `tests` — только проверки отсутствия поля и отказа на него.
- Сверх задач: `down()`/`up()` миграции проверены на `getid_test` вручную (`migrate:rollback --step=1`,
  затем `migrate`) — `RefreshDatabase` гоняет только `up()`. Веб-форма типа молча отбрасывает
  `format_template` — это закреплено тестом `KeyTypeFormTest`, как описано в data-model.md.
- `tests/Concurrency/ConcurrentIssueTest` падает на защите окна старта («booted after the start mark»)
  при load average хоста выше ~30: 50 процессов не успевают загрузиться за `START_DELAY_MS` = 5 с. На
  load ~27 и ниже набор зелёный; к пакету не относится, `START_DELAY_MS` не менялся.
