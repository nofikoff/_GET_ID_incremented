# MCP Contract: get-id

**Date**: 2026-09-20 | **Plan**: [plan.md](./plan.md) | **REST-аналог**: [rest-api.yaml](./rest-api.yaml)

Сервер регистрируется как обычный маршрут Laravel и авторизуется тем же токеном, что REST:

```php
// routes/api.php
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp', GetIdServer::class)->middleware(['auth:sanctum', 'throttle:getid']);
```

`getid` — именованный limiter, общий с REST и ключуемый по идентификатору токена, а не
пользователя: порог FR-020a считается на токен, и два независимых лимита по 60 на двух группах
дали бы 120.

Подключение на стороне клиента:

```bash
claude mcp add --scope user --transport http get-id https://id.x3mal.com/mcp \
  --header "Authorization: Bearer <token>"
```

`--scope user` — сервер виден во всех проектах клиента, а не только в текущем (умолчание
`local`). Сессия, уже запущенная в момент подключения, сервер сама не подхватывает —
`/reload-plugins` или перезапуск сессии.

Инструментов три, и они отображаются на REST один к одному. Домен общий: tool вызывает тот же
`SequenceIssuer`, что и контроллер, поэтому FR-024 («результат через MCP совпадает с результатом
через REST») выполняется по построению, а не проверкой равенства двух реализаций.

## Сквозное ограничение: сервер не видит локальный git

MCP-сервер удалённый. У него нет доступа ни к рабочей копии, ни к `.git`, ни к конфигурации
клиента. Адрес origin читает **клиент** командой `git remote get-url origin` и передаёт строкой.

Это сказано в `description` каждого инструмента, который принимает `origin` или `project_key`
(FR-023). Без этого ассистент начинает угадывать ключ проекта по имени каталога — и угадывает
правдоподобно и неверно.

## resolve_project

**Описание для модели**: «Определить ключ проекта get-id по адресу origin git-репозитория и
узнать, зарегистрирован ли он. Вызывайте это первым, до запроса номера. Адрес получите на своей
стороне: `git remote get-url origin` — сервер к вашему репозиторию доступа не имеет.»

**Входная схема**:

```php
public function schema(JsonSchema $schema): array
{
    return [
        'origin' => $schema->string()
            ->description('Вывод `git remote get-url origin`, как есть. Принимаются SSH- и HTTPS-формы.')
            ->required(),
    ];
}
```

**Выход** (`Response::structured`: тот же JSON текстом плюс `structuredContent`; `Response::json` в `laravel/mcp` 1.0 помечен `@internal`):

```json
{
  "project_key": "gitlab.cas.ai/team/backend",
  "registered": true,
  "active": true,
  "name": "Backend",
  "types": [
    { "code": "ADR", "name": "Architecture Decision Record", "next_number": 13 },
    { "code": "spec", "name": "Specification", "next_number": 4 }
  ],
  "hint": null
}
```

Незарегистрированный проект — не ошибка инструмента, а штатный ответ: `registered: false`, пустой
`types` и `hint` с текстом для человека. `Response::error` возвращается только когда адрес вообще не
разбирается как адрес репозитория.

**REST-аналог**: `GET /v1/projects/resolve`.

## next_id

**Описание для модели**: «Получить следующий свободный номер для документа в проекте get-id.
Идемпотентно: повторный вызов с той же темой вернёт тот же номер и `is_new: false`, поэтому
безопасно повторять после сбоя. `project_key` берите из ответа `resolve_project`, не составляйте
сами.»

**Входная схема**:

```php
public function schema(JsonSchema $schema): array
{
    return [
        'project_key' => $schema->string()
            ->description('Ключ проекта из resolve_project. Не угадывайте его по имени каталога.')
            ->required(),
        'type' => $schema->string()
            ->description('Код типа ключа, включённого в проекте: ADR, spec и т. п. Перечень — в ответе resolve_project.')
            ->required(),
        'name' => $schema->string()
            ->description('Тема документа, например add-oauth-auth. Регистр и вид разделителя значения не имеют.')
            ->required(),
    ];
}
```

**Выход**:

```json
{
  "project_key": "gitlab.cas.ai/team/backend",
  "type": "ADR",
  "name": "add-oauth-auth",
  "sequence_number": 12,
  "formatted_id": "ADR-0012",
  "is_new": true,
  "created_at": "2026-09-20T14:30:00Z"
}
```

`Response::error` — когда проект не зарегистрирован, выведен из обращения или тип в нём не
включён либо погашен. Текст начинается с кода причины из REST-контракта, затем называет
нормализованный ключ и следующий шаг:

```text
project_not_registered: проект gitlab.cas.ai/team/sandbox не зарегистрирован. Попросите администратора get-id завести его, указав этот ключ.
```

Код в начале — ради FR-024: причина отказа та же, что `error.code` в REST, и клиент, который
разбирает ответ программно, опирается на неё, а не на формулировку. Остальной текст адресован
модели и через неё человеку, поэтому он говорит, что делать, а не только что произошло.

Аргументы проверяет FormRequest REST-аналога, поэтому аргумент вне схемы (`additionalProperties:
false` в `rest-api.yaml`) — `Response::error` с тем же текстом, что `message` ответа 422 у REST.

**REST-аналог**: `POST /v1/sequence/next`.

## list_identifiers

**Описание для модели**: «Показать уже выданные номера в проекте по типу ключа, от новых к старым.
Используйте, чтобы проверить, не заводился ли документ на эту тему раньше.»

**Входная схема**:

```php
public function schema(JsonSchema $schema): array
{
    return [
        'project_key' => $schema->string()
            ->description('Ключ проекта из resolve_project.')
            ->required(),
        'type' => $schema->string()
            ->description('Код типа ключа.')
            ->required(),
    ];
}
```

**Выход**:

```json
{
  "project_key": "gitlab.cas.ai/team/backend",
  "type": "ADR",
  "items": [
    { "sequence_number": 2, "name": "add-docker-support", "formatted_id": "ADR-0002", "created_at": "2026-09-19T14:30:00Z" },
    { "sequence_number": 1, "name": "init-project", "formatted_id": "ADR-0001", "created_at": "2026-09-18T10:00:00Z" }
  ]
}
```

**REST-аналог**: `GET /v1/sequence/list`.

## Что в MCP-поверхности намеренно отсутствует

Административные операции — заведение проекта, заведение типа, включение типа в проекте — через MCP
не публикуются. Причина не в правах (они всё равно проверялись бы Policy), а в том, что справочник
пополняется решением человека: реестр закрыт именно затем, чтобы ассистент не заводил проекты
самостоятельно (принцип III конституции, FR-011). Администратор работает через веб-интерфейс или
REST.

## Тестирование

- Каждый tool проверяется тестом `laravel/mcp` на схему и на успешный вызов.
- Для каждой пары «tool ↔ endpoint» — тест, сверяющий результаты на одних и тех же входных данных
  (FR-024).
- Отдельный тест: вызов без заголовка авторизации отвергается на уровне маршрута, до инструмента.
