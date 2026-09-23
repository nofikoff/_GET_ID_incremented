# Contract: веб-консоль администратора

Все маршруты справочника — группа `web` (сессия, CSRF), `auth`, `EnsureAdministrator` (research.md
R2). Исключение — `/help` (FR-020): только `auth`, доступен любому вошедшему сотруднику, без
`EnsureAdministrator`. Сотрудник без роли получает 403 на каждом маршруте справочника, включая
несуществующий id. Успешная запись — redirect с flash-сообщением; отказ валидации — redirect back,
ошибки под полями, `old()` в форме. Тексты отказов — те же, что в REST
([rest-api.yaml](../../001-incremental-id-registry/contracts/rest-api.yaml)).

| Метод | Путь | Имя | Поля | Успех |
|-------|------|-----|------|-------|
| GET | `/admin/projects` | `admin.projects.index` | — | список |
| GET | `/admin/projects/create` | `admin.projects.create` | — | форма |
| POST | `/admin/projects` | `admin.projects.store` | `repo_url`, `name`, `description?` | → `admin.projects.show` |
| GET | `/admin/projects/{project}` | `admin.projects.show` | query `page_<key_type_id>` | карточка |
| PATCH | `/admin/projects/{project}` | `admin.projects.update` | `name`, `description?`, `is_active` | → `admin.projects.show` |
| PUT | `/admin/projects/{project}/key-types` | `admin.projects.key-types.update` | `types[<code>][enabled]`, `types[<code>][seed_sequence]?` | → `admin.projects.show`, flash «Набор типов проекта сохранён.» или, если ничего не изменилось, «Изменений нет.» |
| GET | `/admin/key-types` | `admin.key-types.index` | — | список |
| GET | `/admin/key-types/create` | `admin.key-types.create` | — | форма |
| POST | `/admin/key-types` | `admin.key-types.store` | `code`, `name`, `format_template`, `description?` | → `admin.key-types.index` |
| GET | `/admin/key-types/{keyType}/edit` | `admin.key-types.edit` | — | форма |
| PATCH | `/admin/key-types/{keyType}` | `admin.key-types.update` | `name`, `format_template`, `description?`, `is_active` | → `admin.key-types.index` |
| GET | `/admin/logs` | `admin.logs.index` | query `user_id?`, `from?`, `to?`, `surface?` (`rest`\|`mcp`), `page` | журнал |
| GET | `/help` | `help` | — | команда подключения MCP, ссылка на `tokens.index`, перечень инструментов (FR-020) |

## Поля ошибок, которых нет в REST

| Форма | Ошибка | Поле |
|-------|--------|------|
| типы проекта | seed ниже выданного (`SeedBelowIssued`) | `types.<code>.seed_sequence` |
| типы проекта | тип выведен из обращения (`RetiredKeyType`, `EnableableKeyType`) | `types.<code>.enabled` |
| типы проекта | seed указан у типа без отметки — «Отметьте тип, чтобы задать начальный номер.» | `types.<code>.seed_sequence` |
| журнал | `to` раньше `from` — «Дата «по» не может быть раньше даты «с».» | `to` |
| журнал | сотрудник не найден — стандартный текст правила `exists` | `user_id` |

Гость на любом маршруте — redirect на `login`. Роль проверяется на каждом запросе (FR-016).

## Подтверждения

| Действие | Когда спрашивается |
|----------|--------------------|
| вывод проекта из обращения | `is_active` меняется с «да» на «нет» |
| вывод типа из обращения | то же для типа |
| отправка набора типов | хотя бы один включённый тип снят или включённая пара погашенного типа будет выключена; перечень — в тексте |

Возврат проекта или типа в обращение подтверждения не требует.

Подтверждение — только на стороне браузера (research.md R4).

## Отличие формы от REST

Поле, которого форма не объявляет (`repo_url` или `key` в изменении проекта, `code` в изменении типа),
отбрасывается, а не отвергается: закрытое тело REST на форму не переносится (research.md R1 — оно
отвергло бы `_token` и `_method`). Значение такого поля не меняется, FR-004 и FR-011 соблюдены.
Изменение отвечает и на `PUT`, и на `PATCH` — так регистрирует `Route::resource`; маршрутов удаления нет.

## Что не меняется

REST `/api/v1/admin/*` и MCP `/mcp` — пути, тела, ответы и тексты отказов прежние (FR-018).
