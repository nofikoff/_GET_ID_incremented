# Implementation Plan: Веб-консоль администратора

**Branch**: `001-incremental-id-registry` | **Date**: 2026-09-23 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/002-admin-web-console/spec.md`

## Summary

Экраны `/admin/*` становятся полноценной консолью: формы проектов, типов и их включения, просмотр
выданных номеров и журнала обращений. Изменения идут через тот же прикладной слой, что и REST:
операции справочника выносятся из API-контроллеров в actions, правила валидации — в наборы правил,
общие для API-запросов (закрытое тело, JSON 422) и веб-запросов (redirect back с ошибками). Схема
данных не меняется. Решения и отвергнутые варианты — [research.md](./research.md).

## Technical Context

**Language/Version**: PHP 8.3, Laravel 13 (версии — `composer.json`)

**Primary Dependencies**: пакетов не добавляется; Blade, FormRequest, Gate, пагинатор Eloquent

**Storage**: MySQL 8 в разработке и тестах, MariaDB 10.4 в production; схема не меняется

**Testing**: Pest, feature-тесты в `tests/Feature/Admin/Web/`, набор в Docker (`make test`)

**Target Platform**: LAMP за Cloudflare (README §Развёртывание), без сборки фронтенда

**Project Type**: web-service, серверный рендер

**Performance Goals**: не проектируются — единицы администраторов, десятки записей справочника

**Constraints**: поведение REST и MCP не меняется (FR-018): существующие тесты остаются зелёными
без правки ожиданий; вёрстка — inline-стили `layouts/app.blade.php`, JavaScript только инлайн для
подтверждений

**Scale/Scope**: 8 экранов, 6 форм, 4 action, 5 наборов правил, 2 читающих запроса

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Принцип | Как план его выполняет | Статус |
|---------|------------------------|--------|
| I. Идемпотентность выдачи | выдача не затрагивается; консоль не выдаёт номера | PASS |
| II. Номер не переиспользуется | удаления нет ни в одной форме; снятие типа — `is_enabled = false` через `EnabledKeyTypes`; просмотр реестра только читает | PASS |
| III. Реестр закрытый | ключ выводится из адреса в `CreateProject`, руками не вводится; проект заводит только администратор | PASS |
| IV. Framework-native | FormRequest, Gate, пагинатор, redirect с `withErrors`, журнал приложения — штатные механизмы; самописного нет | PASS |
| V. Конкурентность — параллельный тест | новых конкурентных путей нет: гонка заведения закрыта `RethrowsUniqueConflictAsValidation` и UNIQUE, seed — блокировкой в `EnabledKeyTypes` | PASS |
| VI. Один слой домена на транспорты | веб — третий транспорт к тем же actions и `EnabledKeyTypes`; контроллеры без логики справочника | PASS |
| VII. Тесты в Docker до готовности | `make test` — гейт каждого шага | PASS |

Post-design re-check: PASS — [data-model.md](./data-model.md) не вводит сущностей и колонок,
[contracts/web-console.md](./contracts/web-console.md) не меняет REST и MCP.

## Project Structure

### Documentation (this feature)

```text
specs/002-admin-web-console/
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
├── Actions/Registry/                  # CreateProject, UpdateProject, CreateKeyType, UpdateKeyType (+ запись FR-019)
├── Domain/Project/EnabledKeyTypes.php # без изменений: общий для REST и веба
├── Http/
│   ├── Controllers/Api/Admin/         # худеют до вызова actions; ответы не меняются
│   ├── Controllers/Web/Admin/         # ProjectController, ProjectKeyTypeController, KeyTypeController, ApiLogController
│   ├── Requests/Api/Admin/            # rules() берут наборы из Validation/
│   ├── Requests/Web/Admin/            # веб-запросы: те же наборы, redirect back
│   └── Validation/                    # ProjectRules, KeyTypeRules, ProjectKeyTypeRules — одно место правил
├── Queries/                           # IssuedIdentifiers (по паре, пагинация), ApiLogQuery (фильтры журнала)
resources/views/admin/
├── projects/{index,create,show}.blade.php
├── key-types/{index,create,edit}.blade.php
├── logs/index.blade.php
└── partials/                          # поле с ошибкой, подтверждение
routes/web.php                         # /admin за EnsureAdministrator (research.md R2)
tests/Feature/Admin/Web/               # формы, отказы, 403, паритет с REST
docs/adr/adr-002-mcp-surface-boundary.md  # следствие о веб-экранах
```

**Structure Decision**: одно Laravel-приложение, раскладка пакета 001. Новые каталоги —
`app/Actions/Registry/`, `app/Http/Validation/`, `app/Queries/`; остальное добавляется в
существующие.

## Complexity Tracking

Нарушений конституции нет.
