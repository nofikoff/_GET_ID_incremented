# Research: удаление последнего выданного номера

## R1. Путь удаления мимо `AppendOnlyQueryBuilder`

**Decision**: у `AppendOnlyQueryBuilder` появляется метод `withdraw(): int`, который вызывает
`parent::delete()`. `delete()`, `update()` и `upsert()` по-прежнему бросают `RegistryIsAppendOnly`, так
что `$identifier->delete()`, массовое и relation-удаление остаются закрытыми. `withdraw()` вызывает
только `App\Domain\Sequence\SequenceWithdrawer`, и только как `Identifier::query()->whereKey($id)->toBase()->withdraw()`:
метода нет в `$passthru` Eloquent-builder, поэтому вызов без `toBase()` уходит в `__call()`, который
отбрасывает результат и возвращает сам builder, а не число строк (analyze F1).

**Rationale**: удаление идёт через Eloquent-запрос с его `where`, и у него есть одно имя, которое
находит grep. Запрет по умолчанию сохраняется: чтобы удалить, код должен назвать операцию явно, а
случайный `delete()` бросает как раньше.

**Alternatives considered**:
- `DB::table('identifiers')->delete()` в домене — работает, но удаление тогда ничем не отличается от
  зачистки в наборе `Concurrency`, и его не найти по имени;
- снять запрет с `delete()` целиком — любой `->delete()` в коде снова удаляет запись реестра (FR-009).

## R2. Сериализация с выдачей

**Decision**: `SequenceWithdrawer::withdraw(Identifier $identifier)` в одной `DB::transaction(…, 3)`:

1. `ProjectKeyType::lockForUpdate()` на строке пары этого номера — та же цель блокировки, что у
   `SequenceIssuer` (ADR-001). Это первый запрос транзакции;
2. запрошенная запись перечитывается по ключу; её нет → `ModelNotFoundException` («не найдено»), даже
   если в паре остались другие номера — так её уже снял другой администратор (analyze F2);
3. наибольший `sequence_number` пары обычным чтением; он больше номера запрошенной записи →
   `NotTheLastIdentifier` с `formatted_id` хвоста;
4. `toBase()->withdraw()` по ключу записи;
5. `last_sequence` пары = наибольший `sequence_number` оставшихся записей или 0.

**Rationale**: любая вставка и любое снятие в паре идут под блокировкой её строки, поэтому после шага 1
в паре нет незакоммиченной выдачи. Шаги 2, 3 и 5 — consistent read, и они видят все выдачи,
закоммиченные до получения блокировки: в InnoDB read view создаётся первым неблокирующим чтением
транзакции, а оно идёт после шага 1. `DB::transaction()` начинает транзакцию без `WITH CONSISTENT
SNAPSHOT`, так что раньше read view не возникает. Поэтому перед шагом 1 в транзакции не должно быть ни
одного чтения — это инвариант класса, и docblock его называет.

Locking read по `identifiers` отвергнут (analyze F6): запрос хвоста по диапазону индекса `(project_id,
key_type_id, sequence_number)` ставит gap lock до первой записи следующей пары, и выдача первого
номера в соседней пустой паре ждала бы снятия — это нарушает FR-005 «разные пары друг друга не ждут».
Consistent read блокировок не ставит, поэтому снятие держит ровно одну строку `project_key_type`.

Deadlock с `EnabledKeyTypes` (блокирует все пары проекта) и с `SequenceIssuer` невозможен: снятие
держит одну строку пары и больше ничего не блокирует. Повтор на deadlock — тот же, что у выдачи.

**Alternatives considered**:
- блокировать только запись хвоста без строки пары — выдача не берёт блокировку на `identifiers`, так
  что выдача и удаление прошли бы одновременно, и `last_sequence` разошёлся бы с перечнем;
- locking read хвоста — gap lock на соседнюю пару (см. выше).

## R3. Где домен, где след

**Decision**:
- `App\Domain\Sequence\SequenceWithdrawer` — только правило хвоста и счётчик; возвращает
  `WithdrawnIdentifier` (формат, тема, номер, новый `last_sequence`, следующий номер).
- `App\Actions\Registry\WithdrawIdentifier` — вызывает домен и пишет след через
  `RegistryChangeLog::withdrawn()`, операция `withdraw_identifier`, сущность `project`.
- Выдачу `SequenceIssuer` не трогаем: она по-прежнему единственное место выделения номера.

**Rationale**: раскладка пакета 002 — справочник меняют actions, след пишет `RegistryChangeLog`
(specs/002-admin-web-console/research.md R5). Отдельный доменный класс, а не метод `SequenceIssuer`:
у выдачи и снятия общая только блокировка, и её правило уже записано в ADR-001.

## R4. Маршрут и доступ

**Decision**: `Route::resource('projects.identifiers', IdentifierController::class)->only(['destroy'])->scoped()`
внутри группы `/admin` с `EnsureAdministrator`. Получается `DELETE /admin/projects/{project}/identifiers/{identifier}`,
имя `admin.projects.identifiers.destroy`.

**Rationale**: `scoped()` ищет номер через `Project::identifiers()`, поэтому номер чужого проекта — 404
без ручной проверки. `EnsureAdministrator` стоит в priority list до `SubstituteBindings`
(specs/002-admin-web-console/research.md R2), и сотрудник без роли получает 403 раньше поиска (FR-007).

## R5. Отказ в вебе

**Decision**: `NotTheLastIdentifier` контроллер превращает в `ValidationException` под ключом
`identifiers.<key_type_id>`, redirect back; ошибка выводится над таблицей пары. Текст: «Удалить можно
только последний номер пары: сейчас это {formatted_id}.» Номер, исчезнувший до запроса, — 404 (scoped
binding) или, если исчез между binding и блокировкой, тот же 404 из домена.

**Rationale**: так консоль отдаёт все свои отказы (`ProjectKeyTypeController`); отдельный 409 в
браузере показал бы голую страницу ошибки.

`NotTheLastIdentifier` не наследует `DomainRejection`: тот описывает отказ REST и MCP с кодом
контракта, а удаления в этих поверхностях нет (FR-010).

## R6. Кнопка только у хвоста

**Decision**: форма с крестиком рисуется у первой строки первой страницы пары (`$loop->first &&
$identifiers->onFirstPage()`): перечень отсортирован по `sequence_number` по убыванию. Подтверждение
через `onsubmit="return confirm(@js(…))"`, как у остальных разрушающих действий консоли
(specs/002-admin-web-console/research.md R4).

## R7. Доказательство гонки

**Decision**: скрытая команда `getid:withdraw {project_key} {type} {number} {--at=}` рядом с
`getid:issue` вызывает `SequenceWithdrawer` и печатает JSON `{withdrawn: bool, last_sequence, waited_ms}`;
отказ «не последний» — `withdrawn: false`, код выхода 0. `tests/Concurrency/ConcurrentWithdrawTest.php`:
выданы 1..10; одновременно стартуют снятие 10 и пять выдач новых тем. Проверка: номера в реестре идут
подряд без повторов, `last_sequence` равен максимуму; если снятие прошло — выдачи получили 10..14,
если нет — 11..15.

**Rationale**: гонку в этом репозитории доказывают только отдельные процессы с общей меткой старта
(CLAUDE.md §Тесты и окружение); команда — та же точка входа, что для выдачи.

## R8. Номер ADR

**Decision**: номер для ADR, закрывающего удаление любого номера, удаление без отката и мягкое снятие,
берётся через get-id (`next_id`, тип `ADR`, origin этого репозитория). Проект не заведён или тип не
включён → администратор заводит его, а номер не берётся листингом `docs/adr/`. До выдачи в документах
он называется ADR-003.
