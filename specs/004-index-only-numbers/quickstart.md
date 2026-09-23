# Quickstart: сервис выдаёт только номер

```bash
make test   # все четыре suite
```

После выкладки:

1. MCP `next_id` для нового slug → в ответе `sequence_number`, нет `formatted_id`.
2. Описание `next_id` и instructions сервера называют `adr-043-<slug>.md` и `specs/043-<slug>/`.
3. `/help` — блок для `CLAUDE.md` с тем же правилом.
4. `/admin/key-types/create` — полей шаблона нет. Карточка проекта — нет колонки «Идентификатор».
