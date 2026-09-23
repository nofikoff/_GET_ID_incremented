# Research: сервис выдаёт только номер

## R1. Удаление колонок

**Decision**: новая миграция `drop_format_columns`: `key_types.format_template` и
`identifiers.formatted_id` удаляются в `up()`. `down()` возвращает обе колонки nullable, без данных.

**Rationale**: приложение выложено 2026-09-23, в production-базе есть данные, поэтому исходные
миграции не правятся. Строка `CLAUDE.md` §Тесты и окружение, говорившая «ещё не развёрнуто», устарела
и переписана этим пакетом (analyze C1). Запрет `down()` миграции реестра защищает выданные номера от повторной выдачи; колонка
отформатированного имени номеров не несёт — её возврат пустой ничего не освобождает. NOT NULL в
`down()` не восстанавливается: вернуть значения неоткуда, а пустая строка выдавала бы себя за имя.

**Alternatives considered**: оставить колонки и перестать их читать — spec, Assumptions.

## R2. Где живёт правило трёх цифр

**Decision**: в трёх текстах для потребителя и нигде в коде:
- блок `CLAUDE.md` на странице справки (`resources/views/help.blade.php`);
- `#[Description]` инструмента `next_id` (`app/Mcp/Tools/NextIdTool.php`);
- `instructions` сервера (`app/Mcp/Servers/GetIdServer.php`), шаг 3.

**Rationale**: описание инструмента и instructions сервера ассистент получает при подключении, справку
— человек, который копирует блок в `CLAUDE.md`. Сервис правило не применяет и не проверяет: иначе
конвенция потребителя снова оказалась бы в сервисе.

## R3. Что уходит из кода

| Что | Судьба |
|-----|--------|
| `App\Domain\KeyType\IdentifierFormat`, `InvalidFormatTemplate`, `App\Rules\FormatTemplate`, `tests/Unit/IdentifierFormatTest.php`, `tests/Feature/Sequence/FormattedIdTest.php` | удаляются |
| `IssuedIdentifier::$formattedId`, `WithdrawnIdentifier::$formattedId`, `NotTheLastIdentifier::$lastFormattedId` | удаляются; отказ и след называют `код + номер` |
| `KeyTypeRules` | без `format_template`; закрытое тело REST отвергает поле как неизвестное (FR-006) |
| `KeyTypeResource`, `IssuedIdentifierResource`, `IdentifierListResource` | без поля |
| `KeyTypeSeeder`, `KeyTypeFactory`, `IdentifierFactory` | без поля |
| формы и список типов, карточка проекта | без поля и колонки |

## R4. Имя номера в консоли и следе

**Decision**: «`{code} {number}`» — «ADR 33». След снятия: `changes.<code>.sequence_number => [33, null]`
вместо `identifier => ['ADR-0033', null]`.

**Rationale**: без шаблона единственное общее имя номера — тип и число; ведущие нули в консоли были бы
тем же шаблоном, только зашитым в разметку.

## R5. Номер ADR

**Decision**: номер ADR «сервис выдаёт только индекс» берётся через get-id (`next_id`, тип `ADR`),
файл — `docs/adr/adr-NNN-<slug>.md` с трёхзначным номером.
