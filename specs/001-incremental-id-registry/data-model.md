# Data Model: Реестр инкрементальных идентификаторов

**Date**: 2026-09-20 | **Plan**: [plan.md](./plan.md)

Семь таблиц: шесть предметных плюс стандартная таблица токенов Sanctum. Все — InnoDB,
`utf8mb4_unicode_ci`.

## Легенда

| Таблица | Что в ней |
|---------|-----------|
| `users` | сотрудники, вошедшие через Google |
| `personal_access_tokens` | токены доступа Sanctum, несколько на пользователя |
| `projects` | справочник репозиториев, ведёт администратор |
| `key_types` | справочник родов документов, ведёт администратор |
| `project_key_type` | какие типы включены в каком проекте; здесь же счётчик выдачи |
| `identifiers` | реестр выданных номеров, неизменяем |
| `api_logs` | журнал обращений |

## users

| Колонка | Тип | Ограничения |
|---------|-----|-------------|
| `id` | `bigint unsigned` | PK, AI |
| `name` | `varchar(255)` | |
| `email` | `varchar(255)` | UNIQUE, домен `@cas.ai` проверяется до вставки |
| `google_id` | `varchar(255)` | UNIQUE, nullable |
| `avatar_url` | `varchar(2048)` | nullable |
| `role` | `enum('member','admin')` | NOT NULL, default `member` |
| `deactivated_at` | `timestamp` | nullable — сотрудник выведен из обращения (FR-021b) |
| `created_at`, `updated_at` | `timestamp` | |

Пароля нет: вход только через Google, локальной аутентификации не существует. `email_verified_at`
не заводится по той же причине — подтверждение адреса делает Google.

Пользователь не удаляется: на него ссылаются `projects.created_by` и `identifiers.created_by` с
`ON DELETE RESTRICT`, и авторство выданных номеров обязано пережить уход сотрудника.
Деактивация проставляет `deactivated_at` и сносит все токены этого пользователя; попытка снять роль
у последнего администратора отвергается (FR-021a).

## personal_access_tokens

Стандартная миграция Sanctum без изменений: полиморфная связь с `users`, `name`, `token` (хеш,
UNIQUE), `abilities`, `last_used_at`, `expires_at`. Хранится хеш — значение показывается один раз при
создании (FR-019a). Отзыв — удаление строки; это не нарушает принцип II, потому что неудаляемым
объявлен реестр номеров, а не токены.

## projects

| Колонка | Тип | Ограничения |
|---------|-----|-------------|
| `id` | `bigint unsigned` | PK, AI |
| `key` | `varchar(255)` | UNIQUE, нормализованный ключ вида `gitlab.cas.ai/team/repo` |
| `name` | `varchar(255)` | NOT NULL, человекочитаемое |
| `repo_url` | `varchar(2048)` | исходный адрес, как ввёл администратор |
| `description` | `text` | nullable |
| `is_active` | `boolean` | NOT NULL, default `true` |
| `created_by` | `bigint unsigned` | FK → `users.id`, `ON DELETE RESTRICT` |
| `created_at`, `updated_at` | `timestamp` | |

`key` вычисляется из `repo_url` через `ProjectKey` и не редактируется вручную: иначе ключ разойдётся
с origin, по которому его ищет клиент. Строка длиной 255 под UNIQUE помещается в лимит индекса
InnoDB при `utf8mb4` (767 байт при `DYNAMIC` row format — 1024 байта на 255 символов не влезают,
поэтому индекс объявляется с префиксом 191 либо колонка сужается; решается в миграции).

## key_types

| Колонка | Тип | Ограничения |
|---------|-----|-------------|
| `id` | `bigint unsigned` | PK, AI |
| `code` | `varchar(32)` | UNIQUE, например `ADR`, `spec` |
| `name` | `varchar(255)` | NOT NULL |
| `format_template` | `varchar(255)` | NOT NULL, валидируется при сохранении |
| `description` | `text` | nullable |
| `is_active` | `boolean` | NOT NULL, default `true` |
| `created_at`, `updated_at` | `timestamp` | |

`format_template` принимает только плейсхолдеры `{number}`, `{number:0Nd}` и `{name}`, и обязан
содержать номер (FR-013a). Значения по умолчанию из seeder: `ADR-{number:04d}` и
`{number:03d}-{name}`.

## project_key_type

| Колонка | Тип | Ограничения |
|---------|-----|-------------|
| `id` | `bigint unsigned` | PK, AI |
| `project_id` | `bigint unsigned` | FK → `projects.id`, `ON DELETE RESTRICT` |
| `key_type_id` | `bigint unsigned` | FK → `key_types.id`, `ON DELETE RESTRICT` |
| `seed_sequence` | `int unsigned` | NOT NULL, default 0 — последний номер, занятый вне сервиса |
| `last_sequence` | `int unsigned` | NOT NULL, default 0 — последний выданный сервисом |
| `created_at`, `updated_at` | `timestamp` | |

- UNIQUE `(project_id, key_type_id)`.
- Следующий номер = `GREATEST(seed_sequence, last_sequence) + 1`.
- `seed_sequence` не может быть уменьшен ниже `last_sequence` (FR-014b); проверка — в FormRequest и
  повторно в доменном сервисе.

**Это строка, которую берёт `lockForUpdate()`.** Она существует с момента включения типа в проекте,
поэтому блокировка всегда имеет цель — в отличие от агрегата по пустому набору строк `identifiers`.
Обоснование выбора целиком — [research.md, R1](./research.md).

## identifiers

| Колонка | Тип | Ограничения |
|---------|-----|-------------|
| `id` | `bigint unsigned` | PK, AI |
| `project_id` | `bigint unsigned` | FK → `projects.id`, `ON DELETE RESTRICT` |
| `key_type_id` | `bigint unsigned` | FK → `key_types.id`, `ON DELETE RESTRICT` |
| `name` | `varchar(255)` | NOT NULL, исходная формулировка первой выдачи |
| `name_slug` | `varchar(255)` | NOT NULL, нормализованный вид |
| `sequence_number` | `int unsigned` | NOT NULL |
| `created_by` | `bigint unsigned` | FK → `users.id`, `ON DELETE RESTRICT`, nullable |
| `created_at`, `updated_at` | `timestamp` | |

- UNIQUE `(project_id, key_type_id, name_slug)` — физическая гарантия идемпотентности (FR-002).
- UNIQUE `(project_id, key_type_id, sequence_number)` — физическая гарантия неповторяемости номера
  (FR-001, FR-004).
- INDEX `(project_id, key_type_id, sequence_number DESC)` — выдача перечня в порядке убывания
  (FR-006).

Таблица неизменяема после вставки: в домене нет ни `update`, ни `delete`. `down()` миграции сносит
таблицу целиком — это откат создания схемы, а не операция над живыми данными.

## api_logs

| Колонка | Тип | Ограничения |
|---------|-----|-------------|
| `id` | `bigint unsigned` | PK, AI |
| `user_id` | `bigint unsigned` | FK → `users.id`, `ON DELETE SET NULL`, nullable |
| `method` | `varchar(10)` | |
| `endpoint` | `varchar(255)` | путь маршрута |
| `payload` | `json` | nullable, параметры запроса, обрезаются до 4 КБ при записи |
| `status_code` | `smallint unsigned` | |
| `duration_ms` | `int unsigned` | |
| `ip_address` | `varchar(45)` | вмещает IPv6 |
| `created_at` | `timestamp` | INDEX — по нему чистит регламентная команда |

`user_id` обнуляется при удалении пользователя, а не блокирует его: журнал не является реестром и
не обязан переживать владельца записи.

## Связи

```text
users 1──∞ personal_access_tokens
users 1──∞ projects            (created_by)
users 1──∞ identifiers         (created_by, nullable)
users 1──∞ api_logs            (user_id, nullable)

projects   1──∞ project_key_type ∞──1 key_types
projects   1──∞ identifiers      ∞──1 key_types
```

`project_key_type` — не чистая таблица связи: она несёт состояние нумерации пары, поэтому у неё
собственный ключ и временные метки, а не составной PK.

## Правила валидации

| Правило | Источник | Где проверяется |
|---------|----------|-----------------|
| Почта оканчивается на `@cas.ai` | FR-018 | колбэк Socialite, до создания пользователя |
| `repo_url` разбирается в ключ | FR-008 | `ProjectKey`, FormRequest создания проекта |
| Тема непуста после нормализации | FR-007a | `DocumentName`, FormRequest выдачи |
| Шаблон содержит `{number}` и только известные плейсхолдеры | FR-013a | `IdentifierFormat`, FormRequest типа |
| `seed_sequence >= last_sequence` | FR-014b | FormRequest включения типа + доменный сервис |
| Проект активен и тип включён и активен | FR-015 | `SequenceIssuer`, до открытия транзакции |
| Тип, выведенный из обращения глобально, нельзя включить в проекте | Edge Cases | FormRequest включения типа |

## Состояния

Жизненный цикл есть только у проекта и типа ключа, и он двухпозиционный:

```text
active ──(администратор гасит)──> inactive ──(администратор возвращает)──> active
```

В состоянии `inactive` выдача новых номеров отвергается, чтение перечня продолжает работать
(FR-015, Edge Cases). Возврат в `active` продолжает нумерацию с прежнего места: `last_sequence` при
гашении не трогается.

Выданный идентификатор состояний не имеет — он существует с момента вставки и не меняется.
