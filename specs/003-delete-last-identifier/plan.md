# Implementation Plan: Удаление последнего выданного номера

**Branch**: `001-incremental-id-registry` | **Date**: 2026-09-23 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/003-delete-last-identifier/spec.md`

## Summary

Администратор снимает последний номер пары крестиком на карточке проекта. Доменный
`SequenceWithdrawer` берёт ту же блокировку строки `project_key_type`, что и выдача, проверяет под ней,
что номер — хвост пары, удаляет его явным `AppendOnlyQueryBuilder::withdraw()` и откатывает
`last_sequence` до нового хвоста. Action пишет след через `RegistryChangeLog`. Схема не меняется, REST
и MCP не меняются. Контракт spec 001/002, принцип II конституции и `CLAUDE.md` переписываются под
новое исключение. Решения — [research.md](./research.md).

## Technical Context

**Language/Version**: PHP 8.3, Laravel 13 (версии — `composer.json`)

**Primary Dependencies**: пакетов не добавляется

**Storage**: MySQL 8 в разработке и тестах, MariaDB 10.4 в production; схема не меняется

**Testing**: Pest; `tests/Feature/Admin/Web/`, `tests/Feature/Sequence/`, `tests/Concurrency/`; `make test` в Docker

**Target Platform**: LAMP за Cloudflare (README §Развёртывание)

**Project Type**: web-service, серверный рендер

**Performance Goals**: не проектируются — единичные ручные операции

**Constraints**: выдача, повтор и перечень в REST и MCP без изменений (FR-010); удаление только через
`SequenceWithdrawer` (FR-009)

**Scale/Scope**: 1 маршрут, 1 контроллер, 1 доменный класс, 1 action, 1 скрытая команда, правка 1 шаблона

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Принцип | Как план его выполняет | Статус |
|---------|------------------------|--------|
| I. Идемпотентность выдачи | повтор существующей записи не меняется; снятая тема выдаётся заново как первая выдача — записи для повтора больше нет (FR-006). Формулировка принципа уточняется той же поправкой, что II (FR-011) | PASS |
| II. Номер не переиспользуется | **нарушается намеренно**: номер, снятый с хвоста администратором, выдаётся снова. Поправка принципа входит в пакет — см. Complexity Tracking | JUSTIFIED |
| III. Реестр закрытый | удаление только администратору, 403 до поиска сущности, 404 на чужой проект | PASS |
| IV. Framework-native | nested resource `->scoped()`, `ValidationException`, `lockForUpdate`, `Log`; обход append-only — именованный метод builder с причиной в research.md R1 | PASS |
| V. Конкурентность — параллельный тест | новый конкурентный путь доказывается `tests/Concurrency/ConcurrentWithdrawTest.php` отдельными процессами с `--at` (research.md R7) | PASS |
| VI. Один слой домена на транспорты | веб — единственный транспорт удаления; правило хвоста и счётчик — в `SequenceWithdrawer`, контроллер без логики | PASS |
| VII. Тесты в Docker до готовности | `make test` — гейт каждого шага | PASS |

Post-design re-check: PASS с тем же обоснованием II — [data-model.md](./data-model.md) не вводит
колонок, [contracts/web-console.md](./contracts/web-console.md) не меняет REST и MCP.

## Project Structure

### Documentation (this feature)

```text
specs/003-delete-last-identifier/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/web-console.md
├── checklists/
└── tasks.md             # /speckit-tasks
```

### Source Code (repository root)

```text
app/
├── Models/Builders/AppendOnlyQueryBuilder.php   # + withdraw()
├── Models/Identifier.php                        # docblock: единственное исключение
├── Domain/Sequence/
│   ├── SequenceWithdrawer.php                   # новый: блокировка пары, правило хвоста, откат счётчика
│   ├── WithdrawnIdentifier.php                  # новый value object
│   └── Exceptions/NotTheLastIdentifier.php      # новый
├── Actions/Registry/
│   ├── WithdrawIdentifier.php                   # новый: домен + след
│   └── RegistryChangeLog.php                    # + withdrawn()
├── Console/Commands/WithdrawIdentifierCommand.php  # getid:withdraw, скрытая, для Concurrency
└── Http/Controllers/Web/Admin/IdentifierController.php  # destroy
resources/views/admin/projects/show.blade.php    # крестик у хвоста, ошибка пары
routes/web.php                                   # projects.identifiers destroy, scoped
tests/
├── Feature/Admin/Web/WithdrawIdentifierTest.php
├── Feature/Sequence/WithdrawTest.php
├── Feature/Models/IdentifierImmutabilityTest.php   # + withdraw — единственный открытый путь
└── Concurrency/ConcurrentWithdrawTest.php (+ процесс снятия в IssueRace или рядом)
docs/adr/adr-NNN-withdraw-last-identifier.md     # номер из get-id (research.md R8)
specs/001-incremental-id-registry/spec.md        # FR-004, FR-016
specs/002-admin-web-console/spec.md              # FR-013
.specify/memory/constitution.md                  # принцип II, преамбула, версия
CLAUDE.md                                        # §Выдача номера
```

**Structure Decision**: раскладка пакетов 001 и 002, новых каталогов нет.

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| Принцип II: номер переиспользуется после снятия с хвоста | тестовые и отменённые сразу после выдачи номера навсегда занимают нумерацию; сегодня их чистят правкой production-базы | удаление без отката счётчика сжигает номера; удаление из середины даёт дыры; мягкое снятие не чистит перечень (spec, Assumptions) |

Поправка конституции — MAJOR (принцип переопределяется несовместимо, Governance): версия 2.0.0,
отдельный commit с названием принципа и причиной. Принцип остаётся строкой-указателем на `CLAUDE.md`
§Выдача номера и ADR.
