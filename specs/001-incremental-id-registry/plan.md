# Implementation Plan: Реестр инкрементальных идентификаторов

**Branch**: `001-incremental-id-registry` | **Date**: 2026-09-20 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/001-incremental-id-registry/spec.md`

## Summary

Laravel-приложение с одной базой, тремя поверхностями доступа (REST, MCP, минимальный web-интерфейс)
и одним доменным сервисом выдачи номеров под ним. Гонка снимается блокировкой строки-счётчика в
таблице связи «проект ↔ тип ключа»: строка существует всегда, поэтому `lockForUpdate()` на ней
сериализует выдачу детерминированно — в отличие от `SELECT MAX(...) FOR UPDATE`, который на пустом
наборе блокировать нечего. Оба уникальных индекса реестра остаются как страховка второго уровня.

MCP-сервер не пишется руками: `laravel/mcp` регистрирует его как обычный маршрут с обычным
middleware, поэтому Bearer-токен Sanctum авторизует REST и MCP одинаково, а три MCP-tool вызывают
тот же сервисный слой, что и контроллеры.

## Technical Context

**Language/Version**: PHP 8.3 (Laravel 13 требует `^8.3`)

**Primary Dependencies**: `laravel/framework` 13.x, `laravel/sanctum` 4.x (Bearer-токены),
`laravel/socialite` 5.x (Google OAuth), `laravel/mcp` 1.x (MCP-сервер по HTTP)

**Storage**: MySQL 8.0 (или MariaDB с поддержкой `SELECT ... FOR UPDATE` на InnoDB)

**Testing**: Pest/PHPUnit — feature-тесты на каждый endpoint, unit-тесты на нормализацию и
форматирование, отдельный тест конкурентности на параллельных процессах

**Target Platform**: Linux, LAMP-хостинг с PHP 8.3 и DocumentRoot в `public/`; локально — Docker
Compose (php-fpm 8.3 + nginx + mysql 8)

**Project Type**: web-service с примыкающим минимальным web-интерфейсом на Blade

**Performance Goals**: единицы запросов в минуту в штатном режиме; выдача номера укладывается в
150 мс p95 при отсутствии конкуренции

**Constraints**: выдача номера атомарна при любом числе параллельных запросов; повторная выдача
ранее выданного номера недопустима ни при каком сценарии

**Scale/Scope**: десятки проектов, единицы типов ключей, тысячи записей реестра, десятки
пользователей

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Принцип | Как план ему соответствует | Статус |
|---------|----------------------------|--------|
| I. Идемпотентность выдачи | Поиск по `(project_id, key_type_id, name_slug)` до открытия транзакции; уникальный индекс на эту тройку делает вторую запись невозможной физически | PASS |
| II. Номер не переиспользуется | В домене нет операции удаления; `down()` миграции реестра не дропает данные; проекты и типы гасятся `is_active` | PASS |
| III. Реестр закрытый | `ProjectKey` value object нормализует origin; сервис отвергает незарегистрированный ключ и невключённый тип до входа в транзакцию | PASS |
| IV. Framework-native | Sanctum, Socialite, `laravel/mcp`, FormRequest, Gate, middleware — вместо ad-hoc; самописного нет ничего, кроме двух value object | PASS |
| V. Параллельный тест | `tests/Concurrency/` запускает N процессов через `Process::pool`, каждый со своим соединением; проверка — N различных номеров | PASS |
| VI. Один слой домена | `SequenceIssuer` вызывается из `SequenceController` и из `NextIdTool`; транспорт содержит только валидацию входа и сериализацию | PASS |
| VII. Тесты в Docker | `make test` поднимает контейнеры и гоняет набор внутри php-контейнера | PASS |

Нарушений нет, раздел Complexity Tracking пуст и удалён.

**Повторная проверка после Phase 1.** Спроектированная схема усиливает принципы I, II и III
физическими ограничениями, а не только кодом: уникальный индекс `(project_id, key_type_id,
name_slug)` делает вторую запись на ту же тему невозможной, уникальный индекс
`(project_id, key_type_id, sequence_number)` — повторную выдачу номера, а FK `ON DELETE RESTRICT`
на `identifiers` не даёт удалить проект или тип, на который ссылается реестр. Новых нарушений
проектирование не внесло.

## Project Structure

### Documentation (this feature)

```text
specs/001-incremental-id-registry/
├── plan.md              # этот файл
├── spec.md              # что и зачем
├── research.md          # Phase 0: решения и отвергнутые альтернативы
├── data-model.md        # Phase 1: схема, индексы, ограничения
├── quickstart.md        # Phase 1: поднять, настроить, получить первый номер
├── contracts/
│   ├── rest-api.yaml    # OpenAPI 3.1 для REST
│   └── mcp-tools.md     # три MCP-tool: схемы и тексты description
├── checklists/
│   └── requirements.md  # качество спецификации
└── tasks.md             # Phase 2, создаётся /speckit-tasks
```

### Source Code (repository root)

Стандартная раскладка Laravel; ниже только те каталоги, куда эта фича действительно пишет.

```text
app/
├── Domain/
│   ├── Sequence/
│   │   ├── SequenceIssuer.php        # выдача номера: единственное место с транзакцией
│   │   ├── IssuedIdentifier.php      # результат выдачи (номер, формат, признак новизны)
│   │   └── Exceptions/               # UnknownProject, TypeNotEnabled, InactiveProject
│   ├── Project/
│   │   └── ProjectKey.php            # value object: нормализация origin
│   ├── KeyType/
│   │   ├── DocumentName.php          # value object: нормализация темы в slug
│   │   └── IdentifierFormat.php      # разбор и применение шаблона форматирования
├── Actions/                           # DeactivateUser — гашение с проверкой «последний админ»
├── Enums/                             # UserRole
├── Models/                            # User, PersonalAccessToken, Project, KeyType,
│                                      # ProjectKeyType, Identifier, ApiLog
├── Providers/                         # AppServiceProvider: Policy, RateLimiter «getid»
├── Http/
│   ├── Controllers/
│   │   ├── Api/                       # SequenceController, ProjectResolveController
│   │   ├── Api/Admin/                 # ProjectController, KeyTypeController,
│   │   │                              # ProjectKeyTypeController
│   │   └── Web/                       # GoogleAuthController, TokenController, админские экраны
│   ├── Middleware/
│   │   └── LogApiRequest.php
│   ├── Requests/                      # FormRequest на каждый пишущий endpoint
│   └── Resources/                     # JsonResource на каждый ответ
├── Mcp/
│   ├── Servers/GetIdServer.php
│   └── Tools/                         # ResolveProjectTool, NextIdTool, ListIdentifiersTool
├── Policies/                          # ProjectPolicy, KeyTypePolicy
└── Console/Commands/                  # PruneApiLogs, IssueIdentifier (тест гонки),
                                       # SetUserRoleCommand, DeactivateUserCommand

database/
├── migrations/                        # семь миграций, см. data-model.md
├── factories/
└── seeders/                           # типы ключей ADR и spec по умолчанию

resources/views/                       # Blade: layout, login, tokens, admin/projects, admin/key-types

routes/
├── web.php                            # вход через Google, кабинет, админские экраны
├── api.php                            # /api/v1/* и Mcp::web('/mcp', GetIdServer::class)
└── console.php                        # планировщик чистки журнала

tests/
├── Unit/                              # ProjectKey, DocumentName, IdentifierFormat
├── Feature/                           # endpoint'ы REST, админские права, вход, токены
├── Mcp/                               # три tool, сверка с REST на тех же входных данных
└── Concurrency/                       # параллельная выдача номеров

config/
└── getid.php                          # домен почты, ADMIN_EMAILS, горизонт журнала

docker/                                # Dockerfile php-fpm, конфиг nginx, init-скрипт тестовой базы
docker-compose.yml
.dockerignore
phpunit.xml                            # MySQL вместо sqlite; suites Unit, Feature, Mcp, Concurrency
Makefile                               # up, init, test, test-race, migrate, fresh
```

**Structure Decision**: стандартная раскладка Laravel плюс каталог `app/Domain/`. Доменный слой
вынесен из `app/Services/` намеренно: в нём лежит единственное место, где открывается транзакция
выдачи, и три value object, которые обязаны иметь unit-тесты без обращения к базе. Раздельные
каталоги `Http/Controllers/Api` и `Mcp/Tools` — это два транспорта над одним и тем же
`SequenceIssuer`, а не два слоя логики.
