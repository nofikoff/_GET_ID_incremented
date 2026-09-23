# Data model: удаление последнего выданного номера

Схема не меняется: ни таблиц, ни колонок, ни миграций.

## Что меняет операция

| Таблица | Изменение |
|---------|-----------|
| `identifiers` | удаляется одна запись — хвост пары `(project_id, key_type_id)` |
| `project_key_type` | `last_sequence` = `sequence_number` новой хвостовой записи пары или 0; `seed_sequence` и `is_enabled` не трогаются |

Инварианты после операции:

- в паре нет записи с `sequence_number` > `last_sequence`, и максимум записей пары равен
  `last_sequence` (или записей нет и `last_sequence` = 0);
- `nextSequence()` = `max(seed_sequence, last_sequence) + 1` без изменений (spec 001, FR-014a);
- `seed_sequence ≥ last_sequence`-проверка spec 001, FR-014b остаётся верной: максимум только уменьшился.

## `WithdrawnIdentifier` (value object домена)

```php
final readonly class WithdrawnIdentifier
{
    public function __construct(
        public string $formattedId,
        public string $name,
        public int $sequenceNumber,
        public int $previousLastSequence,  // счётчик до снятия; расходится с $sequenceNumber, если счётчик уплыл
        public int $lastSequence,  // после снятия
        public int $nextSequence,
    ) {}
}
```

## След (FR-008)

`Log::info('registry change', [...])` тем же `RegistryChangeLog::write()`:

```php
[
    'admin_id' => 1, 'admin_email' => 'admin@cas.ai',
    'operation' => 'withdraw_identifier',
    'entity' => 'project', 'entity_id' => 2,
    'changes' => [
        'spec' => [
            'identifier' => ['033-test2', null],
            'name' => ['test2', null],
            'last_sequence' => [33, 32],
        ],
    ],
]
```
