# Contract changes

Правит контракты пакетов 001–003 на месте; здесь — перечень правок.

## REST (`specs/001-incremental-id-registry/contracts/rest-api.yaml`)

- `IssuedIdentifier`, элемент `listSequence.items`: поле `formatted_id` удаляется из схемы и примеров.
- `KeyType`, `StoreKeyTypeRequest`, `UpdateKeyTypeRequest`: `format_template` удаляется; тело с ним —
  422 по `FailOnUnknownFields`, как любое неизвестное поле.
- Отказ «шаблон без номера» / «неизвестный плейсхолдер» удаляется.

## MCP (`specs/001-incremental-id-registry/contracts/mcp-tools.md`)

- `next_id`, `list_identifiers`: без `formatted_id`.
- Описание `next_id` и instructions сервера: сервис выдаёт только номер; имя документа собирает
  репозиторий, номер дополняется нулями до трёх цифр — `docs/adr/adr-043-<slug>.md`,
  `specs/043-<slug>/`.

## Консоль (`specs/002-…/contracts/web-console.md`, `specs/003-…/contracts/web-console.md`)

- Формы типа ключа: `code`, `name`, `description?`, `is_active`; поле шаблона и его ошибки удаляются.
- Снятие номера: подтверждение «Удалить ADR 33? Номер может быть выдан снова другой теме.», flash
  «Номер ADR 33 удалён. Следующий номер — 33.», отказ «Удалить можно только последний номер пары:
  сейчас это ADR 34.»
