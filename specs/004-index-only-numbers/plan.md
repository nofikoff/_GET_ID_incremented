# Implementation Plan: Сервис выдаёт только номер

**Branch**: `001-incremental-id-registry` | **Date**: 2026-09-23 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/004-index-only-numbers/spec.md`

## Summary

Шаблон типа ключа и отформатированное имя удаляются из хранилища, домена, ответов REST и MCP и из
консоли. Правило вида имени — трёхзначный номер — живёт только в текстах для потребителя: справке,
описании `next_id` и instructions MCP-сервера. Выдача, повтор, гонка и снятие не меняются.
Решения — [research.md](./research.md).

## Technical Context

**Language/Version**: PHP 8.3, Laravel 13 (версии — `composer.json`)

**Primary Dependencies**: без изменений

**Storage**: MySQL 8 / MariaDB 10.4; новая миграция удаляет две колонки ([data-model.md](./data-model.md))

**Testing**: Pest, `make test` в Docker

**Target Platform**: LAMP за Cloudflare

**Project Type**: web-service

**Performance Goals**: не меняются

**Constraints**: только новые миграции; поведение выдачи и снятия неизменно (FR-003)

**Scale/Scope**: −4 класса, 3 resource, 2 домашних value object, 1 правило валидации, 5 шаблонов Blade, ~35 тестов с правкой ожиданий

## Constitution Check

| Принцип | Как план его выполняет | Статус |
|---------|------------------------|--------|
| I. Идемпотентность | повтор возвращает тот же номер, меняется только состав полей | PASS |
| II. Номер не переиспользуется, кроме снятого с хвоста | удаляются колонки, не строки; `down()` не трогает номера | PASS |
| III. Реестр закрытый | не затрагивается | PASS |
| IV. Framework-native | закрытое тело REST отвергает поле штатным `FailOnUnknownFields` | PASS |
| V. Конкурентность | новых конкурентных путей нет; набор `Concurrency` без изменений логики | PASS |
| VI. Один слой домена | `IssuedIdentifier` меняется в одном месте, REST и MCP берут один resource | PASS |
| VII. Тесты в Docker | `make test` | PASS |

Post-design re-check: PASS.

## Project Structure

```text
specs/004-index-only-numbers/{plan,research,data-model,quickstart}.md, contracts/changes.md, tasks.md
database/migrations/2026_09_23_200000_drop_format_columns.php      # новая
app/Domain/KeyType/{IdentifierFormat,InvalidFormatTemplate}.php    # удаляются
app/Rules/FormatTemplate.php                                        # удаляется
app/Domain/Sequence/{SequenceIssuer,SequenceWithdrawer,IssuedIdentifier,WithdrawnIdentifier}.php
app/Domain/Sequence/Exceptions/NotTheLastIdentifier.php
app/Actions/Registry/RegistryChangeLog.php
app/Http/Validation/KeyTypeRules.php, app/Http/Resources/{KeyType,IssuedIdentifier,IdentifierList}Resource.php
app/Http/Controllers/Web/Admin/IdentifierController.php
app/Mcp/Tools/NextIdTool.php, app/Mcp/Servers/GetIdServer.php
app/Models/{KeyType,Identifier}.php, database/{seeders,factories}/…
resources/views/admin/{key-types/*,projects/show}.blade.php, resources/views/help.blade.php
tests/…                                                             # ожидания без шаблона
specs/00{1,2,3}-*/…, CLAUDE.md, docs/adr/adr-NNN-index-only-numbers.md
```

**Structure Decision**: раскладка пакетов 001–003.

## Complexity Tracking

Нарушений конституции нет.
