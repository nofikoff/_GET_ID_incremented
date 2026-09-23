# Data Model: Веб-консоль администратора

Схема не меняется: таблиц, колонок и индексов не добавляется. Консоль читает и меняет сущности spec 001
([data-model.md](../001-incremental-id-registry/data-model.md)).

## Что консоль меняет

| Сущность | Поля, которые меняет консоль | Кем |
|----------|------------------------------|-----|
| `projects` | `repo_url`, `name`, `description` при заведении; `key` выводится; затем `name`, `description`, `is_active` | `CreateProject`, `UpdateProject` |
| `key_types` | `code`, `name`, `description` при заведении (шаблона нет — [spec 004](../004-index-only-numbers/data-model.md)); затем всё, кроме `code`, и `is_active` | `CreateKeyType`, `UpdateKeyType` |
| `project_key_type` | `is_enabled`, `seed_sequence`; строка создаётся при первом включении и не удаляется | `EnabledKeyTypes::replace()` |

`identifiers` и `api_logs` консоль только читает.

## Читающие запросы

- **Выданные номера пары** — `identifiers` по `(project_id, key_type_id)`, `sequence_number desc`,
  с автором (`created_by` → `users`), по 50. Показываются и для выключенных пар.
- **Журнал** — `api_logs`, `created_at desc`, по 50; фильтры `user_id`, диапазон `created_at` по
  датам, поверхность по префиксу `endpoint` (research.md R7). Сотрудник в фильтре — из `users`, включая
  деактивированных: их записи в журнале остаются.

## След изменения (FR-019)

Запись журнала приложения, не таблица:

```text
message: registry change
context: admin_id, admin_email, operation (create|update|set_key_types), entity (project|key_type),
         entity_id, changes {field: [before, after]}
```

Для `set_key_types` `changes` — список пар `code: {enabled: [before, after], seed_sequence: [before, after]}`
только по изменившимся типам. «Было» читается до блокировки строк в `EnabledKeyTypes`, поэтому при
одновременном сохранении одного проекта двумя администраторами след может показать устаревшее «было»;
данные реестра при этом верны. Принято: след отвечает на «кто менял», а не служит источником состояния.
