# Data model: сервис выдаёт только номер

| Таблица | Колонка | Изменение |
|---------|---------|-----------|
| `key_types` | `format_template` | удаляется |
| `identifiers` | `formatted_id` | удаляется |

Остальное без изменений: `identifiers(project_id, key_type_id, name, name_slug, sequence_number,
created_by, timestamps)`, оба UNIQUE-индекса, `project_key_type` целиком.

Миграция: [research.md R1](./research.md).

Веб-формы типа ключа — обычные FormRequest, не закрытое тело: лишнее поле `format_template` в форме
молча отбрасывается, а не отвергается. FR-006 требует отказа только от административного REST (analyze C4).

## Ответы

```json
// next_id / POST api/v1/sequence/next
{"project_key": "gitlab.cas.ai/team/backend", "type": "ADR", "name": "add-oauth-auth",
 "sequence_number": 43, "is_new": true, "created_at": "2026-09-23T19:00:00Z"}

// list_identifiers / GET api/v1/sequence
{"project_key": "…", "type": "ADR",
 "items": [{"sequence_number": 43, "name": "add-oauth-auth", "created_at": "…"}]}

// KeyType (admin REST)
{"id": 1, "code": "ADR", "name": "Architecture Decision Record", "description": null, "is_active": true}
```

## След снятия (spec 003 FR-008, в новой форме)

```php
'changes' => ['ADR' => ['sequence_number' => [33, null], 'name' => ['test2', null], 'last_sequence' => [33, 32]]]
```
