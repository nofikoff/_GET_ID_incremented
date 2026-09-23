# Contract: удаление последнего номера в веб-консоли

Дополняет [contracts/web-console.md пакета 002](../../002-admin-web-console/contracts/web-console.md):
та же группа `web` + `auth` + `EnsureAdministrator`, тот же порядок «403 до поиска сущности».

| Метод | Путь | Имя | Поля | Успех |
|-------|------|-----|------|-------|
| DELETE | `/admin/projects/{project}/identifiers/{identifier}` | `admin.projects.identifiers.destroy` | — (`_method`, `_token`) | → `admin.projects.show`, flash «Номер «{formatted_id}» удалён. Следующий номер — {next}.» |

## Отказы

| Условие | Ответ |
|---------|-------|
| сотрудник без роли, существующий или нет `{identifier}` | 403, одинаковый |
| гость | redirect на `login` |
| `{identifier}` не из `{project}` или не существует | 404 |
| `{identifier}` не последний в паре | redirect back, ошибка под ключом `identifiers.<key_type_id>`: «Удалить можно только последний номер пары: сейчас это {formatted_id}.» |
| нет CSRF-токена | 419, ничего не удалено |

Статус проекта, типа и пары отказа не дают (spec FR-001).

## Подтверждение

| Действие | Текст |
|----------|-------|
| крестик у последнего номера пары | «Удалить {formatted_id}? Номер может быть выдан снова другой теме.» |

## Не меняется

REST (`contracts/rest-api.yaml` пакета 001) и MCP (`contracts/mcp-tools.md`): новых операций нет,
выдача, повтор и перечень ведут себя как раньше (FR-010). Удалённый номер просто отсутствует в
перечне, а его тема выдаётся заново с `is_new: true`.

## Команда (только для набора `Concurrency`)

```
php artisan getid:withdraw {project_key} {type} {number} {--at=}
→ {"withdrawn": true|false, "last_sequence": int, "waited_ms": int}
```

Скрыта, как `getid:issue`. `withdrawn: false` — номер не последний; код выхода 0.
